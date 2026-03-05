<?php

namespace App\Http\Requests\PaymentDetail;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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
            'initials' => ['required', 'string', 'min:3', 'max:40'],
            'is_active' => ['required', 'boolean'],
            'daily_limit' => ['required', 'numeric', 'min:0'],
            'daily_successful_orders_limit' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'max_pending_orders_quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
            'min_order_amount' => ['nullable', 'integer', 'min:0'],
            'max_order_amount' => ['nullable', 'integer', 'min:0', 'gte:min_order_amount'],
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

    protected function deviceIsRequired(): bool
    {
        $paymentDetail = $this->route('paymentDetail');
        $user = $paymentDetail?->user ?? $this->user();

        return ! ($user?->can_work_without_device ?? false);
    }

    protected function prepareForValidation(): void
    {
        $dailySuccessfulOrdersLimit = $this->daily_successful_orders_limit;

        if ($dailySuccessfulOrdersLimit === '' || $dailySuccessfulOrdersLimit === null) {
            $dailySuccessfulOrdersLimit = null;
        }

        $this->merge([
            'daily_successful_orders_limit' => $dailySuccessfulOrdersLimit,
        ]);
    }
}
