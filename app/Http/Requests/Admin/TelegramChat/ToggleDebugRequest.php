<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\TelegramChat;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ToggleDebugRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'debug_enabled' => ['required', 'boolean'],
        ];
    }
}
