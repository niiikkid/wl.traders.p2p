<?php

namespace App\Services\Notification\Channels;

use App\Models\Notification;

interface NotificationChannelInterface
{
    public function send(Notification $notification): void;
}
