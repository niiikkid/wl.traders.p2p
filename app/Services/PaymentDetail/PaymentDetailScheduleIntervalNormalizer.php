<?php

declare(strict_types=1);

namespace App\Services\PaymentDetail;

use App\DTO\PaymentDetailSchedule\PaymentDetailScheduleIntervalData;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PaymentDetailScheduleIntervalNormalizer
{
    /**
     * @param  array<int, array<string, mixed>>  $intervals
     * @return array<int, PaymentDetailScheduleIntervalData>
     */
    public function normalize(array $intervals): array
    {
        $errors = [];
        $normalized = [];

        if ($intervals === []) {
            throw ValidationException::withMessages([
                'intervals' => 'Добавьте хотя бы один рабочий день и интервал.',
            ]);
        }

        foreach ($intervals as $index => $interval) {
            $day_of_week = $interval['day_of_week'] ?? null;
            $starts_at = $this->parseTime($interval['starts_at'] ?? null);
            $ends_at = $this->parseTime($interval['ends_at'] ?? null);

            if (! is_int($day_of_week) && ! (is_string($day_of_week) && ctype_digit($day_of_week))) {
                $errors["intervals.{$index}.day_of_week"] = 'Укажите корректный день недели.';

                continue;
            }

            $day_of_week = (int) $day_of_week;

            if ($day_of_week < 1 || $day_of_week > 7) {
                $errors["intervals.{$index}.day_of_week"] = 'День недели должен быть от 1 до 7.';

                continue;
            }

            if ($starts_at === null) {
                $errors["intervals.{$index}.starts_at"] = 'Укажите корректное время начала в формате HH:mm.';
            }

            if ($ends_at === null) {
                $errors["intervals.{$index}.ends_at"] = 'Укажите корректное время окончания в формате HH:mm.';
            }

            if ($starts_at === null || $ends_at === null) {
                continue;
            }

            if ($starts_at >= $ends_at) {
                $errors["intervals.{$index}.ends_at"] = 'Время окончания должно быть позже времени начала в пределах одних суток.';

                continue;
            }

            $normalized[] = new PaymentDetailScheduleIntervalData(
                day_of_week: $day_of_week,
                starts_at: $starts_at,
                ends_at: $ends_at,
            );
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'intervals' => 'Добавьте хотя бы один рабочий день и интервал.',
            ]);
        }

        usort(
            $normalized,
            fn (PaymentDetailScheduleIntervalData $left, PaymentDetailScheduleIntervalData $right): int => [
                $left->day_of_week,
                $left->starts_at,
            ] <=> [
                $right->day_of_week,
                $right->starts_at,
            ],
        );

        $this->assertNoOverlaps($normalized);
        $this->assertMaxIntervalsPerDay($normalized);

        return $normalized;
    }

    /**
     * @param  array<int, PaymentDetailScheduleIntervalData>  $intervals
     */
    private function assertMaxIntervalsPerDay(array $intervals): void
    {
        /** @var Collection<int, Collection<int, PaymentDetailScheduleIntervalData>> $byDay */
        $byDay = collect($intervals)->groupBy(fn (PaymentDetailScheduleIntervalData $interval): int => $interval->day_of_week);

        foreach ($byDay as $dayIntervals) {
            if ($dayIntervals->count() > 2) {
                throw ValidationException::withMessages([
                    'intervals' => 'Для одного дня можно указать не более двух интервалов.',
                ]);
            }
        }
    }

    /**
     * @param  array<int, PaymentDetailScheduleIntervalData>  $intervals
     */
    private function assertNoOverlaps(array $intervals): void
    {
        /** @var Collection<int, Collection<int, PaymentDetailScheduleIntervalData>> $byDay */
        $byDay = collect($intervals)->groupBy(fn (PaymentDetailScheduleIntervalData $interval): int => $interval->day_of_week);

        foreach ($byDay as $dayIntervals) {
            $previous = null;

            foreach ($dayIntervals as $interval) {
                if ($previous !== null && $interval->starts_at < $previous->ends_at) {
                    throw ValidationException::withMessages([
                        'intervals' => 'Интервалы одного дня не должны пересекаться.',
                    ]);
                }

                $previous = $interval;
            }
        }
    }

    private function parseTime(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^(\d{2}):(\d{2})$/', $value, $matches) === 1) {
            return $this->formatTime((int) $matches[1], (int) $matches[2], 0);
        }

        if (preg_match('/^(\d{2}):(\d{2}):(\d{2})$/', $value, $matches) === 1) {
            return $this->formatTime((int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        return null;
    }

    private function formatTime(int $hours, int $minutes, int $seconds): ?string
    {
        if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59 || $seconds < 0 || $seconds > 59) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
}
