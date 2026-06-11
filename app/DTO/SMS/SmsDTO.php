<?php

namespace App\DTO\SMS;

use App\DTO\BaseDTO;
use App\Enums\SmsType;

readonly class SmsDTO extends BaseDTO
{
    public function __construct(
        public string $sender,
        public string $message,
        public int $timestamp,
        public SmsType $type,
        public int $deviceID
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            sender: $data['sender'],
            message: $data['message'],
            timestamp: $data['timestamp'],
            type: SmsType::from($data['type']),
            deviceID: $data['deviceID'],
        );
    }
}
