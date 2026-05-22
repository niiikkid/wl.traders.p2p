<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\TelegramChatBotServiceContract;
use App\Enums\DisputeStatus;
use App\Exceptions\TelegramChatBotException;
use App\Models\Dispute;
use App\Models\TelegramChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTelegramDisputeResolutionNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const BANK_STATEMENT_DIRECTORY = 'dispute-bank-statements';

    public function __construct(
        private readonly int $disputeId,
        private readonly DisputeStatus $status,
    ) {
        $this->afterCommit();
        $this->onQueue('telegram-chat-automation');
    }

    public function handle(TelegramChatBotServiceContract $telegramChatBot): void
    {
        $dispute = Dispute::query()
            ->with('order')
            ->find($this->disputeId);

        if ($dispute === null || $dispute->order === null) {
            return;
        }

        if (! $dispute->status->equals($this->status)) {
            return;
        }

        if (! $this->status->equals(DisputeStatus::ACCEPTED) && ! $this->status->equals(DisputeStatus::CANCELED)) {
            return;
        }

        $sourceMessage = TelegramChatMessage::query()
            ->with('telegramChat')
            ->where('dispute_id', $dispute->id)
            ->first();

        if ($sourceMessage === null) {
            return;
        }

        $telegramChat = $sourceMessage->telegramChat;

        if ($telegramChat === null) {
            return;
        }

        $apiChatId = $telegramChat->telegram_chat_id;
        $replyToMessageId = $this->resolveReplyToMessageId($sourceMessage->telegram_message_id);
        $orderUuid = $dispute->order->uuid;

        try {
            if ($this->status->equals(DisputeStatus::ACCEPTED)) {
                $this->sendAcceptedNotification($telegramChatBot, $apiChatId, $replyToMessageId, $orderUuid);

                return;
            }

            $this->sendCanceledNotification(
                $telegramChatBot,
                $dispute,
                $apiChatId,
                $replyToMessageId,
                $orderUuid,
                $sourceMessage,
            );
        } catch (TelegramChatBotException $exception) {
            $this->logNotificationFailure($dispute, $sourceMessage, $exception);
        } catch (Throwable $exception) {
            $this->logNotificationFailure($dispute, $sourceMessage, $exception);
        }
    }

    private function sendAcceptedNotification(
        TelegramChatBotServiceContract $telegramChatBot,
        string $apiChatId,
        ?int $replyToMessageId,
        string $orderUuid,
    ): void {
        $telegramChatBot->sendChatMessage(
            $apiChatId,
            "Спор принят.\nUUID сделки: {$orderUuid}",
            $replyToMessageId,
        );
    }

    private function sendCanceledNotification(
        TelegramChatBotServiceContract $telegramChatBot,
        Dispute $dispute,
        string $apiChatId,
        ?int $replyToMessageId,
        string $orderUuid,
        TelegramChatMessage $sourceMessage,
    ): void {
        $caption = "Спор отклонён.\nUUID сделки: {$orderUuid}";
        $documentPath = $this->resolveBankStatementPath($dispute->bank_statement);

        if ($documentPath !== null) {
            try {
                $telegramChatBot->sendChatDocument(
                    $apiChatId,
                    $documentPath,
                    $caption,
                    $replyToMessageId,
                );

                return;
            } catch (TelegramChatBotException $exception) {
                $this->logDocumentSendFailure($dispute, $sourceMessage, $exception);
            }
        }

        $telegramChatBot->sendChatMessage(
            $apiChatId,
            "{$caption}\nНе удалось загрузить выписку.",
            $replyToMessageId,
        );
    }

    private function resolveBankStatementPath(?string $filename): ?string
    {
        if (! is_string($filename) || $filename === '') {
            return null;
        }

        $directory = storage_path(self::BANK_STATEMENT_DIRECTORY);
        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $realDirectory = realpath($directory);
        $realPath = realpath($path);

        if ($realDirectory === false || $realPath === false) {
            return null;
        }

        if (! str_starts_with($realPath, $realDirectory.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realPath;
    }

    private function resolveReplyToMessageId(string $telegramMessageId): ?int
    {
        if ($telegramMessageId === '' || ! ctype_digit($telegramMessageId)) {
            return null;
        }

        return (int) $telegramMessageId;
    }

    private function logDocumentSendFailure(
        Dispute $dispute,
        TelegramChatMessage $sourceMessage,
        TelegramChatBotException $exception,
    ): void {
        Log::warning('Telegram dispute resolution document send failed, using text fallback', [
            'dispute_id' => $dispute->id,
            'order_id' => $dispute->order_id,
            'telegram_chat_message_id' => $sourceMessage->id,
            'error' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);
    }

    private function logNotificationFailure(
        Dispute $dispute,
        TelegramChatMessage $sourceMessage,
        Throwable $exception,
    ): void {
        $telegramChat = $sourceMessage->telegramChat;

        Log::warning('Telegram dispute resolution notification failed', [
            'dispute_id' => $dispute->id,
            'order_id' => $dispute->order_id,
            'order_uuid' => $dispute->order?->uuid,
            'telegram_chat_message_id' => $sourceMessage->id,
            'telegram_chat_id' => $sourceMessage->telegram_chat_id,
            'api_telegram_chat_id' => $telegramChat?->telegram_chat_id,
            'api_telegram_message_id' => $sourceMessage->telegram_message_id,
            'status' => $this->status->value,
            'error' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);
    }
}
