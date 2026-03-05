<?php

namespace App\Http\Requests\Admin\PriceParser;

use App\Enums\MarketEnum;
use Illuminate\Foundation\Http\FormRequest;
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
        $market = MarketEnum::tryFrom(strtolower((string) $this->input('market')));

        $baseRules = [
            'market' => ['required', Rule::in([MarketEnum::BYBIT->value, MarketEnum::BINANCE->value])],
            'buy' => ['required', 'array'],
            'sell' => ['required', 'array'],
        ];

        if ($market && $market->equals(MarketEnum::BINANCE)) {
            return array_merge($baseRules, [
                'buy.country' => ['nullable', 'string', 'max:10'],
                'buy.payment_methods' => ['nullable', 'array'],
                'buy.payment_methods.*' => ['string', 'distinct'],
                'buy.ad_quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
                'buy.min_month_orders' => ['nullable', 'integer', 'min:0'],

                'sell.country' => ['nullable', 'string', 'max:10'],
                'sell.payment_methods' => ['nullable', 'array'],
                'sell.payment_methods.*' => ['string', 'distinct'],
                'sell.ad_quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
                'sell.min_month_orders' => ['nullable', 'integer', 'min:0'],
            ]);
        }

        return array_merge($baseRules, [
            'buy.amount' => ['nullable', 'integer', 'min:1'],
            'buy.payment_methods' => ['nullable', 'array'],
            'buy.payment_methods.*' => ['integer', 'distinct'],
            'buy.ad_quantity' => ['nullable', 'integer', 'min:1', 'max:200'],
            'buy.min_recent_orders' => ['nullable', 'integer', 'min:0'],

            'sell.amount' => ['nullable', 'integer', 'min:1'],
            'sell.payment_methods' => ['nullable', 'array'],
            'sell.payment_methods.*' => ['integer', 'distinct'],
            'sell.ad_quantity' => ['nullable', 'integer', 'min:1', 'max:200'],
            'sell.min_recent_orders' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    public function attributes(): array
    {
        return [
            'buy.amount' => 'объем (покупка)',
            'buy.payment_methods' => 'платежные методы (покупка)',
            'buy.ad_quantity' => 'количество объявлений (покупка)',
            'buy.min_recent_orders' => 'минимум ордеров (покупка)',
            'buy.country' => 'страна (покупка)',
            'buy.min_month_orders' => 'минимум сделок за месяц (покупка)',

            'sell.amount' => 'объем (продажа)',
            'sell.payment_methods' => 'платежные методы (продажа)',
            'sell.ad_quantity' => 'количество объявлений (продажа)',
            'sell.min_recent_orders' => 'минимум ордеров (продажа)',
            'sell.country' => 'страна (продажа)',
            'sell.min_month_orders' => 'минимум сделок за месяц (продажа)',
        ];
    }
}
