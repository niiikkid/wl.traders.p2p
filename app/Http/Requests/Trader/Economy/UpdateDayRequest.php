<?php

declare(strict_types=1);

namespace App\Http\Requests\Trader\Economy;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $numeric = ['nullable', 'numeric', 'between:-999999999999.99999999,999999999999.99999999'];

        return [
            'rate' => $numeric,
            'start_balance' => $numeric,
            'card_uah' => $numeric,
            'end_balance' => $numeric,
            'exchange_balance' => $numeric,
            'circles' => $numeric,
            'arbitrage_usd' => $numeric,
            'expense_uah' => $numeric,
        ];
    }
}
