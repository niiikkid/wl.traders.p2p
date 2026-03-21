<?php

declare(strict_types=1);

namespace App\Services\PaymentDetail;

use App\Models\PaymentDetail;
use App\Models\PaymentDetailEnabledPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaymentDetailEnabledPeriodService
{
    public function syncForPaymentDetail(PaymentDetail $payment_detail, ?Carbon $occurred_at = null): void
    {
        $occurred_at = $occurred_at ?? now();

        DB::transaction(function () use ($payment_detail, $occurred_at) {
            $detail = PaymentDetail::query()
                ->with('user:id,is_online,stop_traffic')
                ->where('id', $payment_detail->id)
                ->lockForUpdate()
                ->first();

            if (! $detail) {
                return;
            }

            $effective_enabled = $this->isEffectiveEnabled($detail);

            $open_period = PaymentDetailEnabledPeriod::query()
                ->where('payment_detail_id', $detail->id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($effective_enabled) {
                if (! $open_period) {
                    PaymentDetailEnabledPeriod::query()->create([
                        'payment_detail_id' => $detail->id,
                        'user_id' => $detail->user_id,
                        'started_at' => $occurred_at,
                        'ended_at' => null,
                    ]);
                }

                return;
            }

            if ($open_period) {
                $close_at = $occurred_at->lt($open_period->started_at)
                    ? $open_period->started_at
                    : $occurred_at;

                $open_period->update([
                    'ended_at' => $close_at,
                ]);
            }
        });
    }

    public function syncForUser(User $user, ?Carbon $occurred_at = null): void
    {
        $occurred_at = $occurred_at ?? now();

        DB::transaction(function () use ($user, $occurred_at) {
            $user = User::query()
                ->where('id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $user) {
                return;
            }

            $details = PaymentDetail::query()
                ->where('user_id', $user->id)
                ->whereNull('archived_at')
                ->get(['id', 'user_id', 'is_active', 'archived_at']);

            $eligible_detail_ids = $details
                ->filter(fn (PaymentDetail $detail) => $this->isEffectiveEnabled($detail, $user))
                ->pluck('id')
                ->values();

            $open_periods = PaymentDetailEnabledPeriod::query()
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->get(['id', 'payment_detail_id', 'started_at']);

            $open_by_detail = $open_periods->keyBy('payment_detail_id');

            $this->closeNotEligibleOpenPeriods($open_periods, $eligible_detail_ids, $occurred_at);
            $this->openEligibleMissingPeriods($eligible_detail_ids, $open_by_detail, $user->id, $occurred_at);
        });
    }

    private function closeNotEligibleOpenPeriods(Collection $open_periods, Collection $eligible_detail_ids, Carbon $occurred_at): void
    {
        $eligible_map = $eligible_detail_ids->flip();

        $open_periods
            ->filter(fn (PaymentDetailEnabledPeriod $period) => ! $eligible_map->has($period->payment_detail_id))
            ->each(function (PaymentDetailEnabledPeriod $period) use ($occurred_at) {
                $close_at = $occurred_at->lt($period->started_at)
                    ? $period->started_at
                    : $occurred_at;

                $period->update([
                    'ended_at' => $close_at,
                ]);
            });
    }

    private function openEligibleMissingPeriods(Collection $eligible_detail_ids, Collection $open_by_detail, int $user_id, Carbon $occurred_at): void
    {
        $rows = $eligible_detail_ids
            ->filter(fn (int $detail_id) => ! $open_by_detail->has($detail_id))
            ->map(fn (int $detail_id) => [
                'payment_detail_id' => $detail_id,
                'user_id' => $user_id,
                'started_at' => $occurred_at,
                'ended_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        if (! empty($rows)) {
            PaymentDetailEnabledPeriod::query()->insert($rows);
        }
    }

    private function isEffectiveEnabled(PaymentDetail $detail, ?User $user = null): bool
    {
        $user = $user ?? $detail->user;

        if (! $user) {
            return false;
        }

        return (bool) $detail->is_active
            && $detail->archived_at === null
            && (bool) $user->is_online
            && ! (bool) $user->stop_traffic;
    }
}
