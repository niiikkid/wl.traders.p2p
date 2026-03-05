<?php

namespace App\Contracts;

use App\Services\Notification\Events\NotificationEventInterface;

interface NotificationServiceContract
{
    public function dispatch(NotificationEventInterface $event): void;
}
