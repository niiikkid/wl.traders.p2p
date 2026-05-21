<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Contracts\TelegramChatMessageParserContract;
use App\Models\TelegramChatMessage;

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
    }
}
