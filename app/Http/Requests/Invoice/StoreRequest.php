<?php

namespace App\Http\Requests\Invoice;

use App\Enums\BalanceType;
use App\Rules\ValidateTRC20Address;
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
            'address' => ['nullable', 'string', 'min:34', 'max:34', new ValidateTRC20Address],
            'amount' => ['required', 'integer', 'min:1'],
            'balance_type' => ['required', Rule::enum(BalanceType::class)],
        ];
    }
}
