<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Profit;

use App\Services\Money\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculateProfitRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|\Illuminate\Contracts\Validation\Rule>>
     */
    public function rules(): array
    {
        return [
            'logic' => ['required', 'string', Rule::in(['in_body', 'out_body'])],
            'amount_currency' => ['required', 'string', Rule::in(Currency::getAllCodes())],
            'amount' => ['required', 'numeric', 'min:0'],
            'exchange_rate' => ['required', 'numeric', 'gt:0'],
            'total_commission_rate' => ['required', 'numeric', 'min:0'],
            'trader_commission_rate' => ['required', 'numeric', 'min:0'],
            'teamleader_commission_rate' => ['required', 'numeric', 'min:0'],
            'teamleader_split_from_service_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
