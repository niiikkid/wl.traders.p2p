<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Contracts\TelegramChatBotServiceContract;
use App\Exceptions\TelegramChatBotException;
use App\Models\TelegramBotSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TelegramChatBotService implements TelegramChatBotServiceContract
{
    private const TELEGRAM_API_BASE = 'https://api.telegram.org/bot';

    public function getSettings(): TelegramBotSetting
    {
        return TelegramBotSetting::query()->firstOrCreate([]);
    }

    public function updateSettings(?string $botToken, bool $regenerateWebhookSecret = false): TelegramBotSetting
    {
        $setting = $this->getSettings();
        $data = [];

        if (is_string($botToken) && $botToken !== '') {
            $this->assertBotTokenIsValid($botToken);
            $data['bot_token'] = $botToken;
            $data['webhook_set_at'] = null;
            $data['webhook_last_error'] = null;
            $data['webhook_metadata'] = null;
        }

        if ($regenerateWebhookSecret || ! $setting->hasWebhookSecret()) {
            $data['webhook_secret'] = $this->generateWebhookSecret();
            $data['webhook_set_at'] = null;
            $data['webhook_last_error'] = null;
        }

        if ($data !== []) {
            $setting->update($data);
        }

        return $setting->refresh();
    }

    public function setupWebhook(): TelegramBotSetting
    {
        $setting = $this->getSettings();

        if (! $setting->hasBotToken()) {
            throw new TelegramChatBotException('Токен Telegram-бота не задан.');
        }

        if (! $setting->hasWebhookSecret()) {
            $setting = $this->updateSettings(botToken: null, regenerateWebhookSecret: true);
        }

        $payload = [
            'url' => $this->webhookUrl(),
            'secret_token' => $setting->webhook_secret,
            'allowed_updates' => ['message'],
            'drop_pending_updates' => true,
        ];

        try {
            $response = $this->client($setting->bot_token)->post('setWebhook', $payload);
            $body = $response->json();

            if (! $response->successful() || ! ($body['ok'] ?? false)) {
                $message = is_string($body['description'] ?? null)
                    ? $body['description']
                    : 'Не удалось установить webhook Telegram.';

                $setting->update([
                    'webhook_last_error' => $message,
                ]);

                throw new TelegramChatBotException($message);
            }

            $setting->update([
                'webhook_set_at' => now(),
                'webhook_last_error' => null,
                'webhook_metadata' => $this->fetchWebhookInfo($setting->bot_token),
            ]);

            return $setting->refresh();
        } catch (TelegramChatBotException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $message = 'Ошибка установки webhook: '.$exception->getMessage();

            $setting->update([
                'webhook_last_error' => $message,
            ]);

            throw new TelegramChatBotException($message, previous: $exception);
        }
    }

    public function webhookUrl(): string
    {
        return route('telegram.chat-automation.webhook', [], true);
    }

    public function refreshWebhookMetadata(): TelegramBotSetting
    {
        $setting = $this->getSettings();

        if (! $setting->hasBotToken()) {
            throw new TelegramChatBotException('Токен Telegram-бота не задан.');
        }

        $setting->update([
            'webhook_metadata' => $this->fetchWebhookInfo($setting->bot_token),
        ]);

        return $setting->refresh();
    }

    protected function assertBotTokenIsValid(string $botToken): void
    {
        $response = $this->client($botToken)->get('getMe');
        $body = $response->json();

        if (! $response->successful() || ! ($body['ok'] ?? false)) {
            $message = is_string($body['description'] ?? null)
                ? $body['description']
                : 'Недействительный токен Telegram-бота.';

            throw new TelegramChatBotException($message);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchWebhookInfo(string $botToken): array
    {
        $response = $this->client($botToken)->get('getWebhookInfo');
        $body = $response->json();

        if (! $response->successful() || ! ($body['ok'] ?? false)) {
            $message = is_string($body['description'] ?? null)
                ? $body['description']
                : 'Не удалось получить статус webhook Telegram.';

            throw new TelegramChatBotException($message);
        }

        $result = $body['result'] ?? null;

        return is_array($result) ? $result : [];
    }

    protected function client(string $botToken): PendingRequest
    {
        $request = Http::baseUrl(self::TELEGRAM_API_BASE.$botToken.'/')
            ->acceptJson()
            ->timeout(30);

        $proxy = config('telegram.proxy');

        if (is_string($proxy) && $proxy !== '') {
            $request = $request->withOptions(['proxy' => $proxy]);
        }

        return $request;
    }

    protected function generateWebhookSecret(): string
    {
        return Str::random(32);
    }
}
