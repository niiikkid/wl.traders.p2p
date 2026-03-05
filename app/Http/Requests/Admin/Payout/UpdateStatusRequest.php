<?php

namespace App\Http\Requests\Admin\Payout;

use App\Enums\PayoutStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(PayoutStatus::class)],
            'trader_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn () => in_array($this->input('status'), [
                    PayoutStatus::TAKEN->value,
                    PayoutStatus::SENT->value,
                    PayoutStatus::COMPLETED->value,
                ], true)),
                'exists:users,id',
            ],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}


