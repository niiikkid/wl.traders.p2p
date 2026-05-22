<?php

namespace App\Http\Requests\Dispute;

use App\Rules\ReceiptFileRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CancelRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:120'],
            'bank_statement' => [
                'required',
                'file',
                'max:5120',
                new ReceiptFileRule,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => 'причина отклонения',
            'bank_statement' => 'выписка по карте',
        ];
    }
}
