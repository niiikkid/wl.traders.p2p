<?php

namespace App\Http\Requests\Invoice;

use App\Enums\BalanceType;
use App\Rules\ValidateTRC20Address;
use Illuminate\Contracts\Validation\ValidationRule;
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'withdrawal_address_id' => [
                'required',
                'integer',
                Rule::exists('withdrawal_addresses', 'id')
                    ->where('user_id', $this->user()?->id),
            ],
            'address' => ['nullable', 'string', 'min:34', 'max:34', new ValidateTRC20Address],
            'amount' => ['required', 'integer', 'min:1'],
            'balance_type' => ['required', Rule::enum(BalanceType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'withdrawal_address_id.required' => 'Выберите адрес вывода.',
            'withdrawal_address_id.exists' => 'Выберите доступный адрес вывода.',
        ];
    }
}
