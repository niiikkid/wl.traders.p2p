<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\TelegramChatFileServiceContract;
use App\Enums\TelegramChatMessageStatus;
use App\Models\TelegramChat;
use App\Models\TelegramChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupTelegramChatDebugMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        private readonly TelegramChat $telegramChat,
    ) {
        $this->afterCommit();
        $this->onQueue('telegram-chat-automation');
    }

    public function handle(TelegramChatFileServiceContract $fileService): void
    {
        $telegramChat = $this->telegramChat->fresh();

        if ($telegramChat === null) {
            return;
        }

        if ($telegramChat->debug_enabled) {
            Log::info('Telegram chat debug cleanup skipped: debug mode re-enabled', [
                'telegram_chat_id' => $telegramChat->id,
            ]);

            return;
        }

        $deletedMessages = 0;
        $deletedAttachments = 0;
        $deletedFiles = 0;

        TelegramChatMessage::query()
            ->where('telegram_chat_id', $telegramChat->id)
            ->where(function ($query): void {
                $query
                    ->where('status', '!=', TelegramChatMessageStatus::PROCESSED)
                    ->orWhereNull('dispute_id');
            })
            ->with('attachments')
            ->orderBy('id')
            ->chunkById(50, function ($messages) use ($fileService, &$deletedMessages, &$deletedAttachments, &$deletedFiles): void {
                foreach ($messages as $message) {
                    foreach ($message->attachments as $attachment) {
                        if ($fileService->deleteStoredFile($attachment)) {
                            $deletedFiles++;
                        }

                        $attachment->delete();
                        $deletedAttachments++;
                    }

                    $message->delete();
                    $deletedMessages++;
                }
            });

        Log::info('Telegram chat debug cleanup completed', [
            'telegram_chat_id' => $telegramChat->id,
            'deleted_messages' => $deletedMessages,
            'deleted_attachments' => $deletedAttachments,
            'deleted_files' => $deletedFiles,
        ]);
    }
}
