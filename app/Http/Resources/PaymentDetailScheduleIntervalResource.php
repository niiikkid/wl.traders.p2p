<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentDetailScheduleIntervalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day_of_week' => $this->day_of_week,
            'starts_at' => $this->formatTime((string) $this->starts_at),
            'ends_at' => $this->formatTime((string) $this->ends_at),
        ];
    }

    private function formatTime(string $time): string
    {
        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            return $time;
        }

        return substr($time, 0, 5);
    }
}
