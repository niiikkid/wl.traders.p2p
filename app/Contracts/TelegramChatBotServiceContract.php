<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\TelegramBotSetting;

interface TelegramChatBotServiceContract
{
    public function getSettings(): TelegramBotSetting;

    public function updateSettings(?string $botToken, bool $regenerateWebhookSecret = false): TelegramBotSetting;

    public function setupWebhook(): TelegramBotSetting;

    public function webhookUrl(): string;

    public function refreshWebhookMetadata(): TelegramBotSetting;
}
