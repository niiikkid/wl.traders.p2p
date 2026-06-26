<?php

namespace App\Http\Requests\PaymentDetail;

use App\Enums\DetailType;
use App\Models\PaymentGateway;
use App\Rules\OwnedPaymentDetailSchedule;
use App\Rules\TraderMaxMinOrderAmount;
use App\Rules\UniquePaymentDetail;
use App\Rules\UniquePhonePaymentDetail;
use App\Services\Money\Currency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if (in_array($this->detail_type, [DetailType::PHONE->value, DetailType::MOBILE_COMMERCE->value], true)) {
            $detail = [
                'required',
                'phone:RU,KZ,UZ,KG,TJ,AZ',
                new UniquePhonePaymentDetail($this->payment_gateway_ids),
                // Дополнительная логика: определяем страну по префиксу
                function ($attribute, $value, $fail) {
                    // Удаляем пробелы/дефисы, чтобы не мешали при проверке
                    $normalized = preg_replace('/\\s+|-/u', '', $value);

                    // Пытаемся определить страну по префиксу
                    $country = $this->guessCountryByPrefix($normalized);

                    if (! $country) {
                        $fail('Не удалось определить страну по номеру телефона.');

                        return;
                    }
                },
            ];
        } elseif (DetailType::CARD->equals($this->detail_type)) {
            $detail = [
                'required',
                'digits:16',
                new UniquePaymentDetail,
            ];
        } elseif (DetailType::E_COM->equals($this->detail_type)) {
            $detail = [
                'required',
                'string',
                'url:https',
                new UniquePaymentDetail,
            ];
        } elseif (DetailType::ACCOUNT_NUMBER->equals($this->detail_type)) {
            $detail = [
                'required',
                'digits:20',
                new UniquePaymentDetail,
            ];
        } elseif (DetailType::IBAN_UAH->equals($this->detail_type)) {
            $detail = [
                'required',
                'string',
                'regex:/^UA\d{27}$/',
                new UniquePaymentDetail,
            ];
        } else {
            $detail = [
                'required',
                'digits:16',
                new UniquePaymentDetail,
            ];
        }

        return [
            'name' => ['required', 'string', 'min:3', 'max:30'],
            'detail' => $detail,
            'detail_type' => ['required', Rule::in(DetailType::values())],
            'initials' => ['required', 'string', 'min:3', 'max:80'],
            'additional_info' => [
                Rule::requiredIf(DetailType::IBAN_UAH->equals($this->detail_type)),
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (! DetailType::IBAN_UAH->equals($this->detail_type) || $value === null || $value === '') {
                        return;
                    }

                    if (! preg_match('/^\d{10}$/', (string) $value)) {
                        $fail('Для IBAN UAH поле должно содержать ИПН из 10 цифр.');
                    }
                },
            ],
            'is_active' => ['required', 'boolean'],
            'daily_limit' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'monthly_limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:100000000',
            ],
            'monthly_limit_reset_day' => [
                'nullable',
                'integer',
                'min:1',
                'max:31',
                Rule::requiredIf(
                    ($this->monthly_limit !== null && $this->monthly_limit !== '')
                    || ($this->monthly_successful_orders_limit !== null && $this->monthly_successful_orders_limit !== '')
                ),
            ],
            'monthly_successful_orders_limit' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'daily_successful_orders_limit' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'min_order_amount' => [
                'nullable',
                'integer',
                'min:0',
                new TraderMaxMinOrderAmount($this->user(), $this->user()),
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $bounds = $this->resolveGatewayBounds();
                    if ($bounds === null) {
                        return;
                    }

                    if ((float) $value < $bounds['min']) {
                        $fail("Минимальная сумма сделки не может быть меньше {$bounds['min']} по выбранному платежному методу.");
                    }

                    if ((float) $value > $bounds['max']) {
                        $fail("Минимальная сумма сделки не может быть больше {$bounds['max']} по выбранному платежному методу.");
                    }
                },
            ],
            'max_order_amount' => [
                'nullable',
                'integer',
                'min:0',
                'gte:min_order_amount',
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $bounds = $this->resolveGatewayBounds();
                    if ($bounds === null) {
                        return;
                    }

                    if ((float) $value > $bounds['max']) {
                        $fail("Максимальная сумма сделки не может быть больше {$bounds['max']} по выбранному платежному методу.");
                    }

                    if ((float) $value < $bounds['min']) {
                        $fail("Максимальная сумма сделки не может быть меньше {$bounds['min']} по выбранному платежному методу.");
                    }
                },
            ],
            'currency' => ['required', 'string', Rule::in(Currency::getAllCodes())],
            'payment_gateway_ids' => ['required', 'array', 'min:1'],
            'payment_gateway_ids.*' => [
                'required',
                'exists:payment_gateways,id',
                function ($attribute, $value, $fail) {
                    $gateway = PaymentGateway::query()->find($value);
                    if ($gateway && $gateway->currency->getCode() !== $this->currency) {
                        $fail('Валюта платежного метода не соответствует выбранной валюте.');
                    }
                },
            ],
            'max_pending_orders_quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
            'order_interval_minutes' => ['nullable', 'integer', 'min:1'],
            'user_device_id' => [
                Rule::requiredIf($this->deviceIsRequired()),
                'nullable',
                'exists:user_devices,id',
            ],
            'payment_detail_schedule_id' => [
                'nullable',
                'integer',
                new OwnedPaymentDetailSchedule($this->user()?->id),
            ],
        ];
    }

    public function attributes()
    {
        return [
            'detail' => __('реквизит'),
            'initials' => __('инициалы'),
            'additional_info' => __('дополнительная информация'),
            'is_active' => __('активность'),
            'daily_limit' => __('дневной лимит'),
            'monthly_limit' => __('месячный лимит'),
            'monthly_limit_reset_day' => __('день сброса месячного лимита'),
            'monthly_successful_orders_limit' => __('месячный лимит по количеству сделок'),
            'daily_successful_orders_limit' => __('дневной лимит по количеству сделок'),
            'min_order_amount' => __('минимальная сумма сделки'),
            'max_order_amount' => __('максимальная сумма сделки'),
            'order_interval_minutes' => __('интервал между сделками'),
            'payment_gateway_ids' => __('платежные методы'),
            'payment_gateway_ids.*' => __('платежный метод'),
            'payment_detail_schedule_id' => __('рабочее расписание'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $detail = $this->detail;
        $additionalInfo = $this->additional_info;
        $dailyLimit = $this->daily_limit;
        $dailySuccessfulOrdersLimit = $this->daily_successful_orders_limit;
        $monthlyLimit = $this->monthly_limit;
        $monthlyLimitResetDay = $this->monthly_limit_reset_day;
        $monthlySuccessfulOrdersLimit = $this->monthly_successful_orders_limit;
        $minOrderAmount = $this->min_order_amount;
        $maxOrderAmount = $this->max_order_amount;
        $paymentDetailScheduleId = $this->payment_detail_schedule_id;

        if ($paymentDetailScheduleId === '' || $paymentDetailScheduleId === null) {
            $paymentDetailScheduleId = null;
        }

        if ($this->detail_type === DetailType::IBAN_UAH->value) {
            $detail = strtoupper(trim((string) $detail));
            $detail = preg_replace('/\s+/', '', $detail);
        } elseif (! DetailType::E_COM->equals($this->detail_type)) {
            $detail = preg_replace('~\D+~', '', $detail);
        }
        if ($additionalInfo === '' || $additionalInfo === null) {
            $additionalInfo = null;
        }
        if ($dailyLimit === '' || $dailyLimit === null) {
            $dailyLimit = null;
        }
        if ($dailySuccessfulOrdersLimit === '' || $dailySuccessfulOrdersLimit === null) {
            $dailySuccessfulOrdersLimit = null;
        }
        if ($monthlyLimit === '' || $monthlyLimit === null) {
            $monthlyLimit = null;
        }
        if ($monthlyLimitResetDay === '' || $monthlyLimitResetDay === null) {
            $monthlyLimitResetDay = null;
        }
        if ($monthlySuccessfulOrdersLimit === '' || $monthlySuccessfulOrdersLimit === null) {
            $monthlySuccessfulOrdersLimit = null;
        }
        if ($minOrderAmount === '' || $minOrderAmount === null) {
            $minOrderAmount = null;
        }
        if ($maxOrderAmount === '' || $maxOrderAmount === null) {
            $maxOrderAmount = null;
        }

        $this->merge([
            'detail' => $detail,
            'additional_info' => $additionalInfo,
            'currency' => strtolower($this->currency),
            'daily_limit' => $dailyLimit,
            'daily_successful_orders_limit' => $dailySuccessfulOrdersLimit,
            'monthly_limit' => $monthlyLimit,
            'monthly_limit_reset_day' => $monthlyLimitResetDay,
            'monthly_successful_orders_limit' => $monthlySuccessfulOrdersLimit,
            'min_order_amount' => $minOrderAmount,
            'max_order_amount' => $maxOrderAmount,
            'payment_detail_schedule_id' => $paymentDetailScheduleId,
        ]);
    }

    protected function deviceIsRequired(): bool
    {
        $user = $this->user();

        return ! ($user?->can_work_without_device ?? false);
    }

    private function guessCountryByPrefix(string $number): ?string
    {
        if (Str::startsWith($number, '77')) {
            return 'KZ'; // Казахстан
        } elseif (Str::startsWith($number, '7')) {
            return 'RU'; // Россия
        } elseif (Str::startsWith($number, '998')) {
            return 'UZ'; // Узбекистан
        } elseif (Str::startsWith($number, '996')) {
            return 'KG'; // Киргизия
        } elseif (Str::startsWith($number, '992')) {
            return 'TJ'; // Таджикистан
        } elseif (Str::startsWith($number, '994')) {
            return 'AZ'; // Азербайджан
        }

        // Если префикс не подходит ни под одну известную страну
        return null;
    }

    private function resolveGatewayBounds(): ?array
    {
        $gatewayIds = collect((array) $this->input('payment_gateway_ids'))
            ->map(static fn (mixed $id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->values();

        if ($gatewayIds->isEmpty()) {
            return null;
        }

        /** @var Collection<int, PaymentGateway> $gateways */
        $gateways = PaymentGateway::query()
            ->whereIn('id', $gatewayIds)
            ->get(['id', 'min_limit', 'max_limit']);

        if ($gateways->isEmpty()) {
            return null;
        }

        $minBound = $gateways->max(static fn (PaymentGateway $gateway) => (float) $gateway->min_limit);
        $maxBound = $gateways->min(static fn (PaymentGateway $gateway) => (float) $gateway->max_limit);

        if ($minBound === null || $maxBound === null || $minBound > $maxBound) {
            return null;
        }

        return [
            'min' => $minBound,
            'max' => $maxBound,
        ];
    }
}
