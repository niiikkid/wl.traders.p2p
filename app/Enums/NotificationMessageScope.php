<?php

namespace App\Enums;

use App\Traits\Enumable;

enum NotificationMessageScope: string
{
    use Enumable;

    case ALL = 'all';
    case WITH_ORDER = 'with_order';

    public function label(): string
    {
        return trans("notifications.message_scopes.{$this->value}");
    }
}
