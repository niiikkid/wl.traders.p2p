<?php

namespace App\Exceptions;

use Exception;

class NotificationChannelNotFoundException extends Exception
{
    public static function make(string $channel): self
    {
        return new self("Notification channel {$channel} not found.");
    }

    public function __construct(string $channel)
    {
        parent::__construct("Notification channel {$channel} not found.");
    }
}
