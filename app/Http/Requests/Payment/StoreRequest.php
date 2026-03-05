<?php

namespace App\Http\Requests\Payment;

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
            'amount' => ['required', 'integer', 'min:0'],
            'payment_gateway' => [
                'required_without:currency',
                'prohibits:currency,manually',
                'exists:payment_gateways,code'
            ],
            'currency' => [
                'required_without:payment_gateway',
                'required_if:manually,1',
                'prohibits:payment_gateway',
                Rule::in(Currency::getAllCodes())
            ],
            'payment_detail_type' => ['nullable', Rule::in(DetailType::values())],
            'merchant_id' => ['required', 'exists:merchants,id'],
            'manually' => [
                'nullable',
                'in:1',
                'prohibits:payment_gateway',
            ],
        ];
    }
}
