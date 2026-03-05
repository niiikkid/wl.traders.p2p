<?php

namespace App\Http\Requests\API\SMS;

use App\Enums\SmsType;
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
            'sender' => ['required', 'string', 'min:1', 'max:256'],
            'message' => ['required', 'string', 'min:1', 'max:512'],
            'timestamp' => ['required', 'integer'],
            'type' => ['required', 'string', Rule::in(SmsType::values())],
        ];
    }
}
