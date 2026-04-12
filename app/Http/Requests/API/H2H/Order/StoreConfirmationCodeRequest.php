<?php

namespace App\Http\Requests\API\H2H\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreConfirmationCodeRequest extends FormRequest
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
            'confirmation_code' => ['required', 'string', 'max:64'],
        ];
    }
}
