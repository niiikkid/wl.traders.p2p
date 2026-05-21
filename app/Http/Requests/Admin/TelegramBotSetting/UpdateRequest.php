<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\TelegramBotSetting;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
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
            'bot_token' => ['nullable', 'string', 'max:500'],
            'regenerate_webhook_secret' => ['sometimes', 'boolean'],
        ];
    }
}
