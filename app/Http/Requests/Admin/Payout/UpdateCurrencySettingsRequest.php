<?php

namespace App\Http\Requests\Admin\Payout;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCurrencySettingsRequest extends FormRequest
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
            'settings' => ['required', 'array'],
            'settings.*.total_commission_rate' => ['required', 'numeric', 'min:0'],
            'settings.*.trader_commission_rate' => ['required', 'numeric', 'min:0'],
            'settings.*.reservation_time_for_payouts' => ['required', 'integer', 'min:1'],
        ];
    }
}
