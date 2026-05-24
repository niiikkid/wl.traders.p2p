<?php

declare(strict_types=1);

namespace App\Services\PaymentDetail;

use App\Enums\PaymentDetailScheduleStatus;
use App\Models\PaymentDetail;
use App\Models\PaymentDetailSchedule;
use App\Models\PaymentDetailScheduleInterval;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PaymentDetailScheduleAvailabilityService
{
    /**
     * @return array{server_timezone: string, server_now: string}
     */
    public function serverClockPayload(?CarbonInterface $at = null): array
    {
        $at = $at ?? now();

        return [
            'server_timezone' => config('app.timezone'),
            'server_now' => $at->toISOString(),
        ];
    }

    /**
     * @return array{
     *     total: int,
     *     with_schedule: int,
     *     counts: array<string, int>,
     *     server_timezone: string,
     *     server_now: string
     * }
     */
    public function buildPaymentDetailSummary(Builder $query, ?CarbonInterface $at = null): array
    {
        $at = $at ?? now();
        $clock = $this->serverClockPayload($at);

        $counts = [];

        foreach (PaymentDetailScheduleStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        $total = (clone $query)->count();
        $not_configured = (clone $query)->whereNull('payment_detail_schedule_id')->count();
        $counts[PaymentDetailScheduleStatus::NotConfigured->value] = $not_configured;

        $schedule_counts = (clone $query)
            ->whereNotNull('payment_detail_schedule_id')
            ->select('payment_detail_schedule_id')
            ->selectRaw('COUNT(*) as payment_details_count')
            ->groupBy('payment_detail_schedule_id')
            ->pluck('payment_details_count', 'payment_detail_schedule_id');

        if ($schedule_counts->isNotEmpty()) {
            $schedules = PaymentDetailSchedule::query()
                ->with('intervals')
                ->whereIn('id', $schedule_counts->keys())
                ->get()
                ->keyBy('id');

            foreach ($schedule_counts as $schedule_id => $detail_count) {
                $detail_count = (int) $detail_count;
                $schedule = $schedules->get($schedule_id);

                if (! $schedule) {
                    $counts[PaymentDetailScheduleStatus::Invalid->value] += $detail_count;

                    continue;
                }

                $status = $this->resolveStatus($schedule, $at)['status'];
                $counts[$status] += $detail_count;
            }
        }

        return [
            'total' => $total,
            'with_schedule' => $total - $not_configured,
            'counts' => $counts,
            'server_timezone' => $clock['server_timezone'],
            'server_now' => $clock['server_now'],
        ];
    }

    public function applyAvailableBySchedule(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at = $at ?? now();
        $weekday = $at->isoWeekday();
        $time = $at->format('H:i:s');

        return $query->where(function (Builder $scheduleQuery) use ($weekday, $time) {
            $scheduleQuery
                ->whereNull('payment_detail_schedule_id')
                ->orWhereHas('schedule.intervals', function (Builder $intervalQuery) use ($weekday, $time) {
                    $intervalQuery
                        ->where('day_of_week', $weekday)
                        ->where('starts_at', '<=', $time)
                        ->where('ends_at', '>', $time);
                });
        });
    }

    public function isAvailableBySchedule(PaymentDetail $payment_detail, ?CarbonInterface $at = null): bool
    {
        if ($payment_detail->payment_detail_schedule_id === null) {
            return true;
        }

        $at = $at ?? now();
        $weekday = $at->isoWeekday();
        $time = $at->format('H:i:s');

        if (! $payment_detail->relationLoaded('schedule')) {
            $payment_detail->load('schedule.intervals');
        }

        $schedule = $payment_detail->schedule;

        if (! $schedule) {
            return false;
        }

        return $this->isScheduleAvailableAt($schedule, $at);
    }

    public function isScheduleAvailableAt(PaymentDetailSchedule $schedule, ?CarbonInterface $at = null): bool
    {
        $at = $at ?? now();

        if (! $schedule->relationLoaded('intervals')) {
            $schedule->load('intervals');
        }

        if ($schedule->intervals->isEmpty()) {
            return false;
        }

        return $this->findCurrentInterval($schedule->intervals, $at->isoWeekday(), $at->format('H:i:s')) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveStatusForPaymentDetail(PaymentDetail $payment_detail, ?CarbonInterface $at = null): ?array
    {
        if ($payment_detail->payment_detail_schedule_id === null) {
            return null;
        }

        if (! $payment_detail->relationLoaded('schedule')) {
            $payment_detail->load('schedule.intervals');
        }

        $schedule = $payment_detail->schedule;

        if (! $schedule) {
            return [
                'id' => $payment_detail->payment_detail_schedule_id,
                'name' => null,
                'server_timezone' => config('app.timezone'),
                'server_now' => ($at ?? now())->toISOString(),
                'status' => PaymentDetailScheduleStatus::Invalid->value,
                'status_label' => PaymentDetailScheduleStatus::Invalid->label(),
                'today_intervals' => [],
                'current_interval' => null,
                'next_interval' => null,
            ];
        }

        return $this->resolveStatus($schedule, $at);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveStatus(PaymentDetailSchedule $schedule, ?CarbonInterface $at = null): array
    {
        $at = $at ?? now();

        if (! $schedule->relationLoaded('intervals')) {
            $schedule->load('intervals');
        }

        if ($schedule->intervals->isEmpty()) {
            return $this->buildStatusPayload(
                schedule: $schedule,
                at: $at,
                status: PaymentDetailScheduleStatus::Invalid,
                today_intervals: collect(),
                current_interval: null,
                next_interval: null,
            );
        }

        $weekday = $at->isoWeekday();
        $time = $at->format('H:i:s');
        $today_intervals = $this->getTodayIntervals($schedule->intervals, $weekday);

        if ($today_intervals->isEmpty()) {
            return $this->buildStatusPayload(
                schedule: $schedule,
                at: $at,
                status: PaymentDetailScheduleStatus::DayOff,
                today_intervals: $today_intervals,
                current_interval: null,
                next_interval: null,
            );
        }

        $current_interval = $this->findCurrentInterval($schedule->intervals, $weekday, $time);

        if ($current_interval !== null) {
            return $this->buildStatusPayload(
                schedule: $schedule,
                at: $at,
                status: PaymentDetailScheduleStatus::Working,
                today_intervals: $today_intervals,
                current_interval: $current_interval,
                next_interval: $this->findNextInterval($today_intervals, $time),
            );
        }

        $next_interval = $this->findNextInterval($today_intervals, $time);

        if ($next_interval === null) {
            return $this->buildStatusPayload(
                schedule: $schedule,
                at: $at,
                status: PaymentDetailScheduleStatus::Finished,
                today_intervals: $today_intervals,
                current_interval: null,
                next_interval: null,
            );
        }

        $had_earlier_interval = $today_intervals->contains(
            fn (PaymentDetailScheduleInterval $interval): bool => $this->normalizeTime((string) $interval->ends_at) <= $time,
        );

        $status = $had_earlier_interval
            ? PaymentDetailScheduleStatus::BreakUntil
            : PaymentDetailScheduleStatus::StartsLater;

        return $this->buildStatusPayload(
            schedule: $schedule,
            at: $at,
            status: $status,
            today_intervals: $today_intervals,
            current_interval: null,
            next_interval: $next_interval,
            break_until_time: $status === PaymentDetailScheduleStatus::BreakUntil
                ? $this->formatDisplayTime((string) $next_interval->starts_at)
                : null,
        );
    }

    /**
     * @param  Collection<int, PaymentDetailScheduleInterval>  $intervals
     * @return Collection<int, PaymentDetailScheduleInterval>
     */
    private function getTodayIntervals(Collection $intervals, int $weekday): Collection
    {
        return $intervals
            ->where('day_of_week', $weekday)
            ->sortBy(fn (PaymentDetailScheduleInterval $interval): array => [
                $this->normalizeTime((string) $interval->starts_at),
            ])
            ->values();
    }

    /**
     * @param  Collection<int, PaymentDetailScheduleInterval>  $intervals
     */
    private function findCurrentInterval(Collection $intervals, int $weekday, string $time): ?PaymentDetailScheduleInterval
    {
        return $this->getTodayIntervals($intervals, $weekday)->first(
            fn (PaymentDetailScheduleInterval $interval): bool => $this->intervalContainsTime($interval, $time),
        );
    }

    /**
     * @param  Collection<int, PaymentDetailScheduleInterval>  $today_intervals
     */
    private function findNextInterval(Collection $today_intervals, string $time): ?PaymentDetailScheduleInterval
    {
        return $today_intervals->first(
            fn (PaymentDetailScheduleInterval $interval): bool => $this->normalizeTime((string) $interval->starts_at) > $time,
        );
    }

    private function intervalContainsTime(PaymentDetailScheduleInterval $interval, string $time): bool
    {
        $starts_at = $this->normalizeTime((string) $interval->starts_at);
        $ends_at = $this->normalizeTime((string) $interval->ends_at);

        return $starts_at <= $time && $ends_at > $time;
    }

    /**
     * @param  Collection<int, PaymentDetailScheduleInterval>  $today_intervals
     * @return array<string, mixed>
     */
    private function buildStatusPayload(
        PaymentDetailSchedule $schedule,
        CarbonInterface $at,
        PaymentDetailScheduleStatus $status,
        Collection $today_intervals,
        ?PaymentDetailScheduleInterval $current_interval,
        ?PaymentDetailScheduleInterval $next_interval,
        ?string $break_until_time = null,
    ): array {
        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'server_timezone' => config('app.timezone'),
            'server_now' => $at->toISOString(),
            'status' => $status->value,
            'status_label' => $status->label($break_until_time),
            'today_intervals' => $today_intervals
                ->map(fn (PaymentDetailScheduleInterval $interval): array => $this->buildIntervalPayload($interval, $at))
                ->values()
                ->all(),
            'current_interval' => $current_interval
                ? $this->buildIntervalPayload($current_interval, $at)
                : null,
            'next_interval' => $next_interval
                ? $this->buildIntervalPayload($next_interval, $at)
                : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildIntervalPayload(PaymentDetailScheduleInterval $interval, CarbonInterface $at): array
    {
        $starts_at = $this->formatDisplayTime((string) $interval->starts_at);
        $ends_at = $this->formatDisplayTime((string) $interval->ends_at);

        return [
            'starts_at' => $starts_at,
            'ends_at' => $ends_at,
            'starts_at_iso' => $this->buildIntervalMoment($at, (string) $interval->starts_at),
            'ends_at_iso' => $this->buildIntervalMoment($at, (string) $interval->ends_at),
        ];
    }

    private function buildIntervalMoment(CarbonInterface $date, string $time): string
    {
        [$hours, $minutes, $seconds] = array_pad(explode(':', $this->normalizeTime($time)), 3, '00');

        return $date->copy()
            ->setTime((int) $hours, (int) $minutes, (int) $seconds)
            ->toISOString();
    }

    private function normalizeTime(string $time): string
    {
        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            return $time.':00';
        }

        return $time;
    }

    private function formatDisplayTime(string $time): string
    {
        return substr($this->normalizeTime($time), 0, 5);
    }
}
