<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Contracts\TelegramChatMessageParserContract;
use App\Models\TelegramChatMessage;
use Illuminate\Support\Facades\Log;

class TelegramChatMessageProcessor
{
    /**
     * @param  iterable<TelegramChatMessageParserContract>  $parsers
     */
    public function __construct(
        private readonly iterable $parsers,
    ) {}

    public function process(TelegramChatMessage $message): void
    {
        $message = $message->fresh(['telegramChat']);

        if ($message === null) {
            return;
        }

        $telegramChat = $message->telegramChat;

        if ($telegramChat === null) {
            return;
        }

        foreach ($this->parsers as $parser) {
            if ($parser->supports($telegramChat->parser_type)) {
                $parser->process($message);

                return;
            }
        }

        Log::warning('Telegram chat message processing skipped: no matching parser', [
            'telegram_chat_message_id' => $message->id,
            'telegram_chat_id' => $telegramChat->id,
            'parser_type' => $telegramChat->parser_type->value,
        ]);
    }
}
