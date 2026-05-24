<?php

declare(strict_types=1);

namespace App\Services\PaymentDetail;

use App\DTO\PaymentDetailSchedule\PaymentDetailScheduleCopyDTO;
use App\DTO\PaymentDetailSchedule\PaymentDetailScheduleIntervalData;
use App\DTO\PaymentDetailSchedule\PaymentDetailScheduleUpsertDTO;
use App\Models\PaymentDetailSchedule;
use App\Utils\Transaction;

class PaymentDetailScheduleService
{
    public function __construct(
        private PaymentDetailScheduleIntervalNormalizer $intervalNormalizer,
    ) {}

    public function create(int $user_id, PaymentDetailScheduleUpsertDTO $data): PaymentDetailSchedule
    {
        return Transaction::run(function () use ($user_id, $data) {
            $intervals = $this->intervalNormalizer->normalize($data->intervals);

            $schedule = PaymentDetailSchedule::query()->create([
                'user_id' => $user_id,
                'name' => $data->name,
            ]);

            $this->replaceIntervals($schedule, $intervals);

            return $schedule->load('intervals');
        });
    }

    public function update(PaymentDetailSchedule $schedule, PaymentDetailScheduleUpsertDTO $data): PaymentDetailSchedule
    {
        return Transaction::run(function () use ($schedule, $data) {
            $intervals = $this->intervalNormalizer->normalize($data->intervals);

            $schedule = PaymentDetailSchedule::query()
                ->where('id', $schedule->id)
                ->lockForUpdate()
                ->firstOrFail();

            $schedule->update([
                'name' => $data->name,
            ]);

            $this->replaceIntervals($schedule, $intervals);

            return $schedule->load('intervals');
        });
    }

    public function copy(PaymentDetailSchedule $schedule, PaymentDetailScheduleCopyDTO $data): PaymentDetailSchedule
    {
        return Transaction::run(function () use ($schedule, $data) {
            $schedule = PaymentDetailSchedule::query()
                ->with('intervals')
                ->where('id', $schedule->id)
                ->lockForUpdate()
                ->firstOrFail();

            $copy = PaymentDetailSchedule::query()->create([
                'user_id' => $schedule->user_id,
                'name' => $data->name,
            ]);

            $copy->intervals()->createMany(
                $schedule->intervals
                    ->map(fn ($interval) => [
                        'day_of_week' => $interval->day_of_week,
                        'starts_at' => $interval->starts_at,
                        'ends_at' => $interval->ends_at,
                    ])
                    ->all(),
            );

            return $copy->load('intervals');
        });
    }

    /**
     * @param  array<int, PaymentDetailScheduleIntervalData>  $intervals
     */
    private function replaceIntervals(PaymentDetailSchedule $schedule, array $intervals): void
    {
        $schedule->intervals()->delete();
        $schedule->intervals()->createMany(
            array_map(
                fn (PaymentDetailScheduleIntervalData $interval): array => $interval->toArray(),
                $intervals,
            ),
        );
    }
}
