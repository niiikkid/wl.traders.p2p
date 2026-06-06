<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Contracts\TelegramChatWebhookIngestionServiceContract;
use App\Enums\TelegramChatMessageStatus;
use App\Enums\TelegramChatMessageType;
use App\Enums\TelegramChatStatus;
use App\Jobs\ProcessTelegramChatMessageJob;
use App\Models\TelegramChat;
use App\Models\TelegramChatMessage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class TelegramChatWebhookIngestionService implements TelegramChatWebhookIngestionServiceContract
{
    private const UUID_PATTERN = '/\b[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\b/';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $updateId = $this->stringifyUpdateId($payload['update_id'] ?? null);

        if ($updateId === null) {
            return;
        }

        if (TelegramChatMessage::query()->where('telegram_update_id', $updateId)->exists()) {
            return;
        }

        $message = $payload['message'] ?? null;

        if (! is_array($message)) {
            return;
        }

        $chat = $message['chat'] ?? null;

        if (! is_array($chat) || ! isset($chat['id'])) {
            return;
        }

        $telegramMessageId = $this->stringifyMessageId($message['message_id'] ?? null);

        if ($telegramMessageId === null) {
            return;
        }

        $apiChatId = (string) $chat['id'];

        try {
            DB::transaction(function () use ($updateId, $message, $chat, $apiChatId, $telegramMessageId): void {
                $telegramChat = $this->resolveTelegramChat($apiChatId, $chat);

                if ($this->messageAlreadyExists($telegramChat->id, $telegramMessageId)) {
                    return;
                }

                $messageType = $this->resolveMessageType($message);
                $text = $this->nullableString($message['text'] ?? null);
                $caption = $this->nullableString($message['caption'] ?? null);
                $isDisputeRelated = $this->isDisputeRelated($message, $text, $caption);

                if (! $telegramChat->debug_enabled && ! $isDisputeRelated) {
                    return;
                }

                $telegramChatMessage = TelegramChatMessage::query()->create([
                    'telegram_chat_id' => $telegramChat->id,
                    'telegram_update_id' => $updateId,
                    'telegram_message_id' => $telegramMessageId,
                    'message_type' => $messageType,
                    'text' => $text,
                    'caption' => $caption,
                    'status' => TelegramChatMessageStatus::RECEIVED,
                    'is_dispute_related' => $isDisputeRelated,
                    'raw_payload' => $message,
                ]);

                ProcessTelegramChatMessageJob::dispatch($telegramChatMessage);
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicateKeyException($exception)) {
                return;
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $chat
     */
    private function resolveTelegramChat(string $apiChatId, array $chat): TelegramChat
    {
        $telegramChat = TelegramChat::query()->firstOrCreate(
            ['telegram_chat_id' => $apiChatId],
            [
                'status' => TelegramChatStatus::PENDING_MODERATION,
                'chat_type' => null,
                'parser_type' => null,
                'debug_enabled' => true,
            ],
        );

        $telegramChat->update([
            'type' => $this->nullableString($chat['type'] ?? null),
            'title' => $this->nullableString($chat['title'] ?? null),
            'username' => $this->nullableString($chat['username'] ?? null),
            'raw_payload' => $chat,
            'last_message_at' => now(),
        ]);

        return $telegramChat->refresh();
    }

    private function messageAlreadyExists(int $telegramChatId, string $telegramMessageId): bool
    {
        return TelegramChatMessage::query()
            ->where('telegram_chat_id', $telegramChatId)
            ->where('telegram_message_id', $telegramMessageId)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function resolveMessageType(array $message): TelegramChatMessageType
    {
        if (is_array($message['photo'] ?? null) && $message['photo'] !== []) {
            return TelegramChatMessageType::PHOTO;
        }

        if (is_array($message['document'] ?? null)) {
            return TelegramChatMessageType::DOCUMENT;
        }

        if (isset($message['text']) && is_string($message['text'])) {
            return TelegramChatMessageType::TEXT;
        }

        return TelegramChatMessageType::UNKNOWN;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function isDisputeRelated(array $message, ?string $text, ?string $caption): bool
    {
        if ($this->hasAttachment($message)) {
            return true;
        }

        return $this->containsUuid($text) || $this->containsUuid($caption);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function hasAttachment(array $message): bool
    {
        if (is_array($message['photo'] ?? null) && $message['photo'] !== []) {
            return true;
        }

        return is_array($message['document'] ?? null);
    }

    private function containsUuid(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return preg_match(self::UUID_PATTERN, $value) === 1;
    }

    private function stringifyUpdateId(mixed $updateId): ?string
    {
        if (is_int($updateId) || is_float($updateId)) {
            return (string) (int) $updateId;
        }

        if (is_string($updateId) && $updateId !== '') {
            return $updateId;
        }

        return null;
    }

    private function stringifyMessageId(mixed $messageId): ?string
    {
        if (is_int($messageId) || is_float($messageId)) {
            $normalized = (int) $messageId;

            if ($normalized <= 0) {
                return null;
            }

            return (string) $normalized;
        }

        if (is_string($messageId) && $messageId !== '') {
            return $messageId;
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function isDuplicateKeyException(QueryException $exception): bool
    {
        $errorCode = $exception->errorInfo[1] ?? null;

        return in_array($errorCode, [1062, 19, 23505], true);
    }
}
