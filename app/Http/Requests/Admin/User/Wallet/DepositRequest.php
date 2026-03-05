<?php

namespace App\Http\Requests\Admin\User\Wallet;

use App\Enums\BalanceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepositRequest extends FormRequest
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
            'amount' => ['required', 'integer', 'min:1'],
            'balance_type' => ['required', Rule::enum(BalanceType::class)],
            'tx_hash' => ['nullable', 'string', 'max:255'],
        ];
    }
}
