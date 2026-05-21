<?php

declare(strict_types=1);

namespace App\Contracts;

interface TelegramChatWebhookIngestionServiceContract
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void;
}
