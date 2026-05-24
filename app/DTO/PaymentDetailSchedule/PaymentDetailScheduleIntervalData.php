<?php

declare(strict_types=1);

namespace App\DTO\PaymentDetailSchedule;

readonly class PaymentDetailScheduleIntervalData
{
    public function __construct(
        public int $day_of_week,
        public string $starts_at,
        public string $ends_at,
    ) {}

    /**
     * @return array{day_of_week: int, starts_at: string, ends_at: string}
     */
    public function toArray(): array
    {
        return [
            'day_of_week' => $this->day_of_week,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
        ];
    }
}
