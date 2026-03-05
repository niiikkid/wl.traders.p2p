<?php

namespace App\Telegram\Commands;

use App\Exceptions\TelegramServiceException;
use App\Exceptions\TelegramStartTokenInvalidException;
use Telegram\Bot\Commands\Command;

class StartCommand extends Command
{
    protected string $name = 'start';

    protected string $description = 'Start command';

    public function handle(): void
    {
        $message = $this->getUpdate()->getMessage();
        $text = trim((string) ($message?->getText() ?? ''));
        $parts = preg_split('/\s+/', $text, 2);
        $token = $parts[1] ?? null;

        if (! $token) {
            $this->replyWithMessage([
                'text' => trans('notifications.telegram.start.missing_token'),
            ]);
            return;
        }

        try {
            $from = $message?->getFrom()?->toArray() ?? [];
            $chatId = (string) ($message?->getChat()?->getId() ?? '');

            services()->telegram()->handleStart($token, $from, $chatId);

            $this->replyWithMessage([
                'text' => trans('notifications.telegram.start.success'),
            ]);
        } catch (TelegramStartTokenInvalidException) {
            $this->replyWithMessage([
                'text' => trans('notifications.telegram.start.invalid_token'),
            ]);
        } catch (TelegramServiceException|\Throwable) {
            $this->replyWithMessage([
                'text' => trans('notifications.telegram.start.error'),
            ]);
        }
    }
}
