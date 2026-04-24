<?php

namespace App\Contracts;

use App\Models\TelegramAccount;
use App\Models\User;

interface TelegramServiceContract
{
    public function getOrCreateForUser(User $user): TelegramAccount;

    public function refreshLink(User $user): TelegramAccount;

    public function handleStart(string $token, array $telegramUser, string $chatId): TelegramAccount;

    public function sendNotification(User $user, string $title, string $body): void;

    public function botUsername(): ?string;

    public function buildDeepLink(TelegramAccount $account): ?string;
}
