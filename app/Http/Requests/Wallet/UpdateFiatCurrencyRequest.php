<?php

namespace App\Http\Requests\Wallet;

use App\Services\Money\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFiatCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'fiat_currency' => [
                'required',
                'string',
                Rule::in(Currency::getAllCodes()),
            ],
        ];
    }
}
