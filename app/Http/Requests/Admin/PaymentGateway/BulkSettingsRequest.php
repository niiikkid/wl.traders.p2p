<?php

namespace App\Http\Requests\Admin\PaymentGateway;

use App\Enums\DetailType;
use App\Services\Money\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkSettingsRequest extends FormRequest
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
            'currency' => ['required', Rule::in(Currency::getAllCodes())],
            'detail_types' => ['sometimes', 'required', 'array'],
            'detail_types.*' => ['nullable', Rule::in(DetailType::values())],
            'min_limit' => ['sometimes', 'required', 'integer', 'min:1'],
            'max_limit' => ['sometimes', 'required', 'integer', 'min:1'],
            'trader_commission_rate_for_orders' => ['sometimes', 'required', 'numeric', 'min:0'],
            'total_service_commission_rate_for_orders' => ['sometimes', 'required', 'numeric', 'min:0'],
            'trader_commission_rate_for_payouts' => ['sometimes', 'required', 'numeric', 'min:0'],
            'total_service_commission_rate_for_payouts' => ['sometimes', 'required', 'numeric', 'min:0'],
            'reservation_time_for_orders' => ['sometimes', 'required', 'integer', 'min:1'],
            'reservation_time_for_payouts' => ['sometimes', 'required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'is_payouts_enabled' => ['sometimes', 'required', 'boolean'],
        ];
    }

    protected function prepareForValidation()
    {
        $currency = strtolower($this->currency ?? '');
        $this->merge([
            'currency' => $currency,
        ]);
    }
}
