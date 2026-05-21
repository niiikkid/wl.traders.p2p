<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\TelegramChat;

use App\Enums\TelegramChatParserType;
use App\Enums\TelegramChatStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'status' => ['sometimes', 'required', Rule::enum(TelegramChatStatus::class)],
            'parser_type' => ['sometimes', 'required', Rule::enum(TelegramChatParserType::class)],
        ];
    }
}
