<?php

namespace App\Services\Notification\Channels;

use App\Enums\NotificationChannel;
use App\Exceptions\NotificationChannelNotFoundException;

class NotificationChannelFactory
{
    public function make(NotificationChannel $channel): NotificationChannelInterface
    {
        return match ($channel) {
            NotificationChannel::IN_APP => app(InAppNotificationChannel::class),
            NotificationChannel::TELEGRAM => app(TelegramNotificationChannel::class),
            default => throw new NotificationChannelNotFoundException($channel->value),
        };
    }
}
