<?php

declare(strict_types=1);

namespace App\DTO\PaymentDetailSchedule;

use App\DTO\BaseDTO;

readonly class PaymentDetailScheduleCopyDTO extends BaseDTO
{
    public function __construct(
        public string $name,
    ) {}

    public static function makeFromRequest(array $data): static
    {
        return new static(
            name: (string) $data['name'],
        );
    }
}
