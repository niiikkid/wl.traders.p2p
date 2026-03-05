<?php

namespace App\Services\Notification\Channels;

use App\Contracts\TelegramServiceContract;
use App\Models\Notification;

class TelegramNotificationChannel implements NotificationChannelInterface
{
    public function __construct(
        protected TelegramServiceContract $telegramService
    ) {}

    public function send(Notification $notification): void
    {
        $this->telegramService->sendNotification($notification);
    }
}
