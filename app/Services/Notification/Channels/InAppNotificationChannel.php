<?php

namespace App\Services\Notification\Channels;

use App\Models\Notification;

class InAppNotificationChannel implements NotificationChannelInterface
{
    public function send(Notification $notification): void
    {
        // Для in-app уведомлений доставка уже выполнена при создании записи.
    }
}
