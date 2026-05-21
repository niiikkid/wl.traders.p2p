<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\TelegramChatStatus;
use App\Models\TelegramChatMessage;
use App\Services\Telegram\TelegramChatMessageProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessTelegramChatMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly TelegramChatMessage $telegramChatMessage,
    ) {
        $this->afterCommit();
        $this->onQueue('default');
    }

    public function handle(TelegramChatMessageProcessor $processor): void
    {
        $message = $this->telegramChatMessage->fresh(['telegramChat']);

        if ($message === null) {
            return;
        }

        $telegramChat = $message->telegramChat;

        if ($telegramChat === null || ! $telegramChat->status->equals(TelegramChatStatus::ACTIVE)) {
            return;
        }

        $processor->process($message);
    }
}
