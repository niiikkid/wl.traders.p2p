<?php

namespace App\Http\Requests\PaymentLink\Dispute;

use Illuminate\Foundation\Http\FormRequest;

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
            'receipt' => [
                'required',
                'mimes:jpeg,jpg,png,pdf',
                'max:2048'
            ],
        ];
    }
}
