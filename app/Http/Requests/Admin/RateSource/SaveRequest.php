<?php

namespace App\Http\Requests\Admin\RateSource;

use App\Enums\RateSourceDirection;
use App\Enums\RateSourceType;
use App\Services\Money\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(RateSourceType::values())],
            'direction' => ['required', Rule::in(RateSourceDirection::values())],
            'quote_currency' => ['required', Rule::in(Currency::getAllCodes())],
            'is_active' => ['boolean'],
            'settings' => ['nullable', 'array'],
            'settings.side' => ['nullable', Rule::in(['buy', 'sell'])],
            'settings.rate' => ['nullable', 'numeric', 'gt:0'],
            'settings.amount' => ['nullable', 'numeric', 'min:0'],
            'settings.country' => ['nullable', 'string', 'max:16'],
            'settings.payment_methods' => ['nullable', 'array'],
            'settings.payment_methods.*' => ['nullable'],
            'settings.ad_quantity' => ['nullable', 'integer', 'min:1', 'max:200'],
            'settings.min_recent_orders' => ['nullable', 'integer', 'min:0'],
            'settings.min_month_orders' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('type') === RateSourceType::MANUAL->value) {
                $rate = $this->input('settings.rate');

                if ($rate === null || $rate === '' || ! is_numeric($rate) || (float) $rate <= 0) {
                    $validator->errors()->add('settings.rate', 'Для ручного источника нужно указать курс больше 0.');
                }
            }
        });
    }
}
