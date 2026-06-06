<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\TelegramChat;

use App\Enums\TelegramChatParserType;
use App\Enums\TelegramChatStatus;
use App\Enums\TelegramChatType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('chat_type') && $this->input('chat_type') === '') {
            $this->merge(['chat_type' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::enum(TelegramChatStatus::class)],
            'chat_type' => ['sometimes', 'nullable', Rule::enum(TelegramChatType::class)],
            'parser_type' => ['sometimes', 'nullable', Rule::enum(TelegramChatParserType::class)],
        ];
    }
}
