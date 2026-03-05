<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Telegram\Bot\Laravel\Facades\Telegram;

class SetTelegramWebhookCommand extends Command
{
    protected $signature = 'app:telegram-webhook';

    protected $description = 'Установить webhook для Telegram бота.';

    public function handle(): int
    {
        $url = config('telegram.webhook_url');

        if (! $url) {
            $this->error('Webhook URL не указан. Передайте URL аргументом или задайте TELEGRAM_WEBHOOK_URL.');

            return self::FAILURE;
        }

        $secret = config('telegram.webhook_secret');
        $payload = ['url' => $url];

        if ($secret) {
            $payload['secret_token'] = $secret;
        }

        $payload['drop_pending_updates'] = true;

        try {
            $response = Telegram::setWebhook($payload);

            if (is_array($response) && isset($response['ok']) && ! $response['ok']) {
                $this->error($response['description'] ?? 'Ошибка установки webhook.');

                return self::FAILURE;
            }

            $this->info('Webhook успешно установлен: '.$url);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Ошибка установки webhook: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
