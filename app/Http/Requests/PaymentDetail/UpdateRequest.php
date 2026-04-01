<?php

namespace App\Http\Requests\PaymentDetail;

use App\Enums\DetailType;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'min:3', 'max:30'],
            'initials' => ['required', 'string', 'min:3', 'max:80'],
            'additional_info' => [
                Rule::requiredIf($this->additionalInfoIsRequired()),
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (! $this->additionalInfoIsRequired() || $value === null || $value === '') {
                        return;
                    }

                    if (! preg_match('/^\d{10}$/', (string) $value)) {
                        $fail('Для IBAN UAH поле должно содержать ИПН из 10 цифр.');
                    }
                },
            ],
            'is_active' => ['required', 'boolean'],
            'daily_limit' => ['required', 'numeric', 'min:0'],
            'monthly_limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:100000000',
                Rule::requiredIf($this->monthly_limit_reset_day !== null && $this->monthly_limit_reset_day !== ''),
            ],
            'monthly_limit_reset_day' => [
                'nullable',
                'integer',
                'min:1',
                'max:28',
                Rule::requiredIf($this->monthly_limit !== null && $this->monthly_limit !== ''),
            ],
            'daily_successful_orders_limit' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'max_pending_orders_quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
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
            'order_interval_minutes' => ['nullable', 'integer', 'min:1'],
            'user_device_id' => [
                Rule::requiredIf($this->deviceIsRequired()),
                'nullable',
                'exists:user_devices,id'
            ],
            'payment_gateway_ids' => ['required', 'array', 'min:1'],
            'payment_gateway_ids.*' => ['required', 'exists:payment_gateways,id'],
        ];
    }

    public function attributes()
    {
        return [
            'initials' => __('инициалы'),
            'additional_info' => __('дополнительная информация'),
            'is_active' => __('активность'),
            'daily_limit' => __('дневной лимит'),
            'monthly_limit' => __('месячный лимит'),
            'monthly_limit_reset_day' => __('день сброса месячного лимита'),
            'daily_successful_orders_limit' => __('дневной лимит по количеству сделок'),
            'min_order_amount' => __('минимальная сумма сделки'),
            'max_order_amount' => __('максимальная сумма сделки'),
            'order_interval_minutes' => __('интервал между сделками'),
            'payment_gateway_ids' => __('платежные методы'),
            'payment_gateway_ids.*' => __('платежный метод'),
        ];
    }

    protected function deviceIsRequired(): bool
    {
        $paymentDetail = $this->route('paymentDetail');
        $user = $paymentDetail?->user ?? $this->user();

        return ! ($user?->can_work_without_device ?? false);
    }

    protected function prepareForValidation(): void
    {
        $dailySuccessfulOrdersLimit = $this->daily_successful_orders_limit;
        $monthlyLimit = $this->monthly_limit;
        $monthlyLimitResetDay = $this->monthly_limit_reset_day;
        $additionalInfo = $this->additional_info;
        $minOrderAmount = $this->min_order_amount;
        $maxOrderAmount = $this->max_order_amount;

        if ($dailySuccessfulOrdersLimit === '' || $dailySuccessfulOrdersLimit === null) {
            $dailySuccessfulOrdersLimit = null;
        }
        if ($additionalInfo === '' || $additionalInfo === null) {
            $additionalInfo = null;
        }
        if ($monthlyLimit === '' || $monthlyLimit === null) {
            $monthlyLimit = null;
        }
        if ($monthlyLimitResetDay === '' || $monthlyLimitResetDay === null) {
            $monthlyLimitResetDay = null;
        }
        if ($minOrderAmount === '' || $minOrderAmount === null) {
            $minOrderAmount = null;
        }
        if ($maxOrderAmount === '' || $maxOrderAmount === null) {
            $maxOrderAmount = null;
        }

        $this->merge([
            'daily_successful_orders_limit' => $dailySuccessfulOrdersLimit,
            'monthly_limit' => $monthlyLimit,
            'monthly_limit_reset_day' => $monthlyLimitResetDay,
            'additional_info' => $additionalInfo,
            'min_order_amount' => $minOrderAmount,
            'max_order_amount' => $maxOrderAmount,
        ]);
    }

    private function additionalInfoIsRequired(): bool
    {
        $paymentDetail = $this->route('paymentDetail');

        return $paymentDetail?->detail_type?->equals(DetailType::IBAN_UAH) ?? false;
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
