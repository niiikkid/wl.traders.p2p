<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Exceptions\TelegramChatBotException;
use App\Models\TelegramBotSetting;

interface TelegramChatBotServiceContract
{
    public function getSettings(): TelegramBotSetting;

    public function updateSettings(
        ?string $botToken,
        bool $regenerateWebhookSecret = false,
        ?string $localWebhookBaseUrl = null,
        bool $updateLocalWebhookBaseUrl = false,
    ): TelegramBotSetting;

    public function setupWebhook(): TelegramBotSetting;

    public function webhookUrl(): string;

    public function refreshWebhookMetadata(): TelegramBotSetting;

    /**
     * @return array{file_path: string, file_size: int|null, file_unique_id: string|null}
     *
     * @throws TelegramChatBotException
     */
    public function getFileInfo(string $fileId): array;

    /**
     * @throws TelegramChatBotException
     */
    public function downloadFileToPath(string $fileId): string;

    /**
     * @throws TelegramChatBotException
     */
    public function sendChatMessage(
        string $chatId,
        string $text,
        ?int $replyToMessageId = null,
    ): void;

    /**
     * @throws TelegramChatBotException
     */
    public function sendChatDocument(
        string $chatId,
        string $documentPath,
        ?string $caption = null,
        ?int $replyToMessageId = null,
    ): void;
}
