<?php

namespace App\Http\Requests\Admin\PaymentGateway;

use App\Enums\DetailType;
use App\Services\Money\Currency;
use Illuminate\Foundation\Http\FormRequest;
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
        return [
            'name' => ['required', 'string', 'min:3', 'max:30'],
            'code' => ['required', 'string', 'min:3', 'max:30', 'unique:payment_gateways,code'],
            'currency' => ['required', Rule::in(Currency::getAllCodes())],
            'detail_types' => ['required', 'array'],
            'detail_types.*' => ['nullable', Rule::in(DetailType::values())],
            'min_limit' => ['required', 'integer', 'min:1'],
            'max_limit' => ['required', 'integer', 'min:1'],
            'trader_commission_rate_for_orders' => ['required', 'numeric', 'min:0'],
            'total_service_commission_rate_for_orders' => ['required', 'numeric', 'min:0'],
            'trader_commission_rate_for_payouts' => ['required', 'numeric', 'min:0'],
            'total_service_commission_rate_for_payouts' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'is_payouts_enabled' => ['required', 'boolean'],
            'is_intrabank' => ['required', 'boolean'],
            'reservation_time_for_orders' => ['required', 'integer', 'min:1'],
            'reservation_time_for_payouts' => ['required', 'integer', 'min:1'],
            'sms_senders' => ['nullable', 'array'],
            'sms_senders.*' => ['required', 'string'],
            'logo' => ['required', 'image', 'mimes:png', 'max:2048', Rule::dimensions()->ratio(1.0)],
        ];
    }

    public function attributes()
    {
        return [
            'name' => __('название'),
            'code' => __('код метода'),
            'currency' => __('валюта'),
            'detail_types' => __('тип реквизитов'),
            'max_limit' => __('макс лимит'),
            'trader_commission_rate_for_orders' => __('комиссия трейдера'),
            'total_service_commission_rate_for_orders' => __('комиссия сервиса'),
            'trader_commission_rate_for_payouts' => __('комиссия трейдера (выплаты)'),
            'total_service_commission_rate_for_payouts' => __('комиссия сервиса (выплаты)'),
            'is_active' => __('активность'),
            'is_payouts_enabled' => __('выплаты доступны'),
            'is_intrabank' => __('внутрибанковский перевод'),
            'reservation_time_for_orders' => __('время на сделку'),
            'reservation_time_for_payouts' => __('время на выплату'),
            'sms_senders' => __('отправители смс/push'),
        ];
    }

    protected function prepareForValidation()
    {
        $currency = strtolower($this->currency ?? '');
        $this->merge([
            'currency' => $currency,
        ]);
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if ($this->is_intrabank && is_array($this->detail_types)) {
                    // Удаляем типы телефонных реквизитов, если установлен внутрибанковский перевод
                    $detail_types = array_filter($this->detail_types, function ($type) {
                        return ! in_array($type, ['phone', 'mobile_commerce'], true);
                    });
                    $this->merge(['detail_types' => $detail_types]);
                }
            }
        ];
    }
}
