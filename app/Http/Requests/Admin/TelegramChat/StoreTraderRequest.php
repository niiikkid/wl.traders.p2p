<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\TelegramChat;

use App\Enums\TelegramChatType;
use App\Models\TelegramChat;
use App\Models\User;
use App\Support\TelegramUsernameNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTraderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'telegram_username' => TelegramUsernameNormalizer::normalize(
                $this->input('telegram_username'),
            ),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var TelegramChat $telegramChat */
        $telegramChat = $this->route('telegramChat');

        return [
            'trader_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                Rule::unique('telegram_chat_traders', 'trader_id')
                    ->where('telegram_chat_id', $telegramChat->id),
            ],
            'telegram_username' => [
                'nullable',
                'string',
                'max:32',
                'regex:'.TelegramUsernameNormalizer::VALIDATION_PATTERN,
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var TelegramChat|null $telegramChat */
            $telegramChat = $this->route('telegramChat');

            if ($telegramChat === null || ! $telegramChat->chat_type?->equals(TelegramChatType::TRADER_TEAM)) {
                $validator->errors()->add('telegram_chat', 'Участников можно добавлять только в чат «Команда трейдеров».');

                return;
            }

            $traderId = $this->integer('trader_id');

            if ($traderId <= 0) {
                return;
            }

            $isTrader = User::query()
                ->role('Trader')
                ->whereKey($traderId)
                ->exists();

            if (! $isTrader) {
                $validator->errors()->add('trader_id', 'Выбранный пользователь не является трейдером.');
            }
        });
    }
}
