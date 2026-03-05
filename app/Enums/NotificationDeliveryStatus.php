<?php

namespace App\Enums;

use App\Traits\Enumable;

enum NotificationDeliveryStatus: string
{
    use Enumable;

    case PENDING = 'pending';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';

    public function label(): string
    {
        return trans("notifications.delivery_statuses.{$this->value}");
    }
}
