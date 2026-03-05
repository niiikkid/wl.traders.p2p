<?php

namespace App\Services\Telegram;

use App\Contracts\TelegramServiceContract;
use App\Exceptions\TelegramAccountNotLinkedException;
use App\Exceptions\TelegramServiceException;
use App\Exceptions\TelegramStartTokenInvalidException;
use App\Models\Notification;
use App\Models\TelegramAccount;
use App\Models\User;
use Illuminate\Support\Str;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramService implements TelegramServiceContract
{
    public function getOrCreateForUser(User $user): TelegramAccount
    {
        $account = TelegramAccount::query()->where('user_id', $user->id)->first();

        if ($account) {
            return $account;
        }

        return TelegramAccount::create([
            'user_id' => $user->id,
            'link_token' => $this->generateToken(),
            'is_active' => false,
        ]);
    }

    public function refreshLink(User $user): TelegramAccount
    {
        $account = $this->getOrCreateForUser($user);

        $account->update([
            'link_token' => $this->generateToken(),
            'chat_id' => null,
            'username' => null,
            'first_name' => null,
            'last_name' => null,
            'is_active' => false,
            'linked_at' => null,
        ]);

        return $account->fresh();
    }

    public function handleStart(string $token, array $telegramUser, string $chatId): TelegramAccount
    {
        $account = TelegramAccount::query()->where('link_token', $token)->first();

        if (! $account) {
            throw new TelegramStartTokenInvalidException();
        }

        $account->update([
            'chat_id' => $chatId,
            'username' => $telegramUser['username'] ?? null,
            'first_name' => $telegramUser['first_name'] ?? null,
            'last_name' => $telegramUser['last_name'] ?? null,
            'is_active' => true,
            'linked_at' => now(),
        ]);

        return $account->fresh();
    }

    public function sendNotification(Notification $notification): void
    {
        $user = $notification->user;
        $account = $this->getOrCreateForUser($user);

        if (! $account->is_active || ! $account->chat_id) {
            throw new TelegramAccountNotLinkedException();
        }

        try {
            Telegram::sendMessage([
                'chat_id' => $account->chat_id,
                'text' => $this->buildMessage($notification),
            ]);
        } catch (\Throwable $e) {
            throw new TelegramServiceException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function botUsername(): ?string
    {
        $default = config('telegram.default', 'default');

        $username = config("telegram.bots.{$default}.username");

        if (! $username || $username === 'default') {
            return null;
        }

        return $username;
    }

    public function buildDeepLink(TelegramAccount $account): ?string
    {
        $botUsername = $this->botUsername();

        if (! $botUsername) {
            return null;
        }

        return "https://t.me/{$botUsername}?start={$account->link_token}";
    }

    protected function buildMessage(Notification $notification): string
    {
        return "{$notification->title}\n\n{$notification->body}";
    }

    protected function generateToken(): string
    {
        return Str::random(32);
    }
}
