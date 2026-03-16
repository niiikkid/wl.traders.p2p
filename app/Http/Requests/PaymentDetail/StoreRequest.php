<?php

namespace App\Http\Requests\PaymentDetail;

use App\Enums\DetailType;
use App\Models\PaymentGateway;
use App\Rules\UniquePaymentDetail;
use App\Rules\UniquePhonePaymentDetail;
use App\Services\Money\Currency;
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
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

                    if (!$country) {
                        $fail('Не удалось определить страну по номеру телефона.');
                        return;
                    }
                },
            ];
        } else if (DetailType::CARD->equals($this->detail_type)) {
            $detail = [
                'required',
                'digits:16',
                new UniquePaymentDetail()
            ];
        } else if (in_array($this->detail_type, [DetailType::NSPK->value, DetailType::E_COM->value], true)) {
            $detail = [
                'required',
                'string',
                'url:https',
                new UniquePaymentDetail(),
            ];
        } else if (DetailType::ACCOUNT_NUMBER->equals($this->detail_type)) {
            $detail = [
                'required',
                'digits:20',
                new UniquePaymentDetail()
            ];
        } else if (DetailType::IBAN_UAH->equals($this->detail_type)) {
            $detail = [
                'required',
                'string',
                'regex:/^UA\d{27}$/',
                new UniquePaymentDetail()
            ];
        } else {
            $detail = [
                'required',
                'digits:16',
                new UniquePaymentDetail()
            ];
        }

        return [
            'name' => ['required', 'string', 'min:3', 'max:30'],
            'detail' => $detail,
            'detail_type' => ['required', Rule::in(DetailType::values())],
            'initials' => ['required', 'string', 'min:3', 'max:40'],
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
            'daily_limit' => ['required', 'integer', 'min:1', 'max:100000000'],
            'daily_successful_orders_limit' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'min_order_amount' => [
                'nullable',
                'integer',
                'min:0',
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
                    $gateway = PaymentGateway::find($value);
                    if ($gateway && $gateway->currency->getCode() !== $this->currency) {
                        $fail('Валюта платежного метода не соответствует выбранной валюте.');
                    }
                }
            ],
            'max_pending_orders_quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
            'order_interval_minutes' => ['nullable', 'integer', 'min:1'],
            'user_device_id' => [
                Rule::requiredIf($this->deviceIsRequired()),
                'nullable',
                'exists:user_devices,id'
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
            'daily_successful_orders_limit' => __('дневной лимит по количеству сделок'),
            'min_order_amount' => __('минимальная сумма сделки'),
            'max_order_amount' => __('максимальная сумма сделки'),
            'order_interval_minutes' => __('интервал между сделками'),
            'payment_gateway_ids' => __('платежные методы'),
            'payment_gateway_ids.*' => __('платежный метод'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $detail = $this->detail;
        $additionalInfo = $this->additional_info;
        $dailySuccessfulOrdersLimit = $this->daily_successful_orders_limit;
        $minOrderAmount = $this->min_order_amount;
        $maxOrderAmount = $this->max_order_amount;

        if ($this->detail_type === DetailType::IBAN_UAH->value) {
            $detail = strtoupper(trim((string) $detail));
            $detail = preg_replace('/\s+/', '', $detail);
        } elseif (! in_array($this->detail_type, [DetailType::NSPK->value, DetailType::E_COM->value], true)) {
            $detail = preg_replace('~\D+~', '', $detail);
        }
        if ($additionalInfo === '' || $additionalInfo === null) {
            $additionalInfo = null;
        }
        if ($dailySuccessfulOrdersLimit === '' || $dailySuccessfulOrdersLimit === null) {
            $dailySuccessfulOrdersLimit = null;
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
            'daily_successful_orders_limit' => $dailySuccessfulOrdersLimit,
            'min_order_amount' => $minOrderAmount,
            'max_order_amount' => $maxOrderAmount,
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
