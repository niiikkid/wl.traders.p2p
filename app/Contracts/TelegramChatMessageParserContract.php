<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\TelegramChatParserType;
use App\Models\TelegramChatMessage;

interface TelegramChatMessageParserContract
{
    public function supports(TelegramChatParserType $parserType): bool;

    public function process(TelegramChatMessage $message): void;
}
