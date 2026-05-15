<?php

namespace App\Http\Requests\Admin\OpenAiSetting;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PromptRequest extends FormRequest
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
            'model' => ['required', 'string', 'max:120'],
            'system_prompt' => ['required', 'string', 'max:10000'],
            'user_prompt' => ['required', 'string', 'max:10000'],
        ];
    }
}
