<?php

namespace App\Enums;

use App\Traits\Enumable;

enum NotificationChannel: string
{
    use Enumable;

    case IN_APP = 'in_app';
    case TELEGRAM = 'telegram';

    public function label(): string
    {
        return trans("notifications.channels.{$this->value}");
    }
}
