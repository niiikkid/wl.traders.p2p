<?php

namespace App\DTO\SMS;

use App\DTO\BaseDTO;

readonly class ShadowSmsLogData extends BaseDTO
{
    public function __construct(
        public int $userId,
        public int $userDeviceId,
        public string $sender,
        public string $message,
        public int $timestamp,
        public string $type,
        public string $filterReason,
        public ?string $matchedSender = null,
        public ?string $matchedStopWord = null,
        public ?int $messageLength = null,
    ) {}
}
