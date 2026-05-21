<?php

declare(strict_types=1);

namespace App\Services\Telegram;

final readonly class TelegramAttachmentReference
{
    public function __construct(
        public string $fileId,
        public ?string $fileUniqueId,
        public ?string $originalName,
        public ?string $mimeType,
        public ?int $fileSize,
    ) {}
}
