<?php

declare(strict_types=1);

namespace App\DTO\PaymentDetailSchedule;

use App\DTO\BaseDTO;

readonly class PaymentDetailScheduleUpsertDTO extends BaseDTO
{
    /**
     * @param  array<int, array{day_of_week: int, starts_at: string, ends_at: string}>  $intervals
     */
    public function __construct(
        public string $name,
        public array $intervals,
    ) {}

    public static function makeFromRequest(array $data): static
    {
        return new static(
            name: (string) $data['name'],
            intervals: array_values($data['intervals'] ?? []),
        );
    }
}
