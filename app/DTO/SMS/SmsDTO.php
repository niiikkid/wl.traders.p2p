<?php

namespace App\DTO\SMS;

use App\DTO\BaseDTO;
use App\Enums\SmsType;
use App\Models\User;
use App\Models\UserDevice;

readonly class SmsDTO extends BaseDTO
{
    public function __construct(
        public string $sender,
        public string $message,
        public int $timestamp,
        public SmsType $type,
        public int $deviceID
    )
    {}

    public static function fromArray(array $data): self
    {
        $data['type'] = SmsType::from($data['type']);
        return new self(...$data);
    }
}
