<?php

namespace App\Services\Notification\Templates;

class NotificationContent
{
    public function __construct(
        public string $title,
        public string $body,
        public array $payload = []
    ) {}
}
