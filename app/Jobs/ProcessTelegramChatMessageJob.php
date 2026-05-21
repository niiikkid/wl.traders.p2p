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
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessTelegramChatMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly TelegramChatMessage $telegramChatMessage,
    ) {
        $this->afterCommit();
        $this->onQueue('telegram-chat-automation');
    }

    public function handle(TelegramChatMessageProcessor $processor): void
    {
        $message = $this->telegramChatMessage->fresh(['telegramChat']);

        if ($message === null) {
            return;
        }

        $telegramChat = $message->telegramChat;

        $processor->storeDebugAttachmentsIfNeeded($message);

        if ($telegramChat === null || ! $telegramChat->status->equals(TelegramChatStatus::ACTIVE)) {
            return;
        }

        try {
            $processor->process($message);
        } catch (Throwable $exception) {
            Log::error('Telegram chat message job failed unexpectedly', [
                'telegram_chat_message_id' => $message->id,
                'telegram_chat_id' => $message->telegram_chat_id,
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }
}
