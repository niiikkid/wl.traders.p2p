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

            $eligible_detail_ids = $this->getEligibleDetailIds($user);

            $open_detail_ids = PaymentDetailEnabledPeriod::query()
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->pluck('payment_detail_id');

            $this->closeNotEligibleOpenPeriods($user->id, $eligible_detail_ids, $occurred_at);
            $this->openEligibleMissingPeriods($eligible_detail_ids, $open_detail_ids, $user->id, $occurred_at);
        });
    }

    private function getEligibleDetailIds(User $user): Collection
    {
        if (! $user->is_online || $user->stop_traffic) {
            return collect();
        }

        return PaymentDetail::query()
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->where('is_active', true)
            ->pluck('id');
    }

    private function closeNotEligibleOpenPeriods(int $user_id, Collection $eligible_detail_ids, Carbon $occurred_at): void
    {
        $query = PaymentDetailEnabledPeriod::query()
            ->where('user_id', $user_id)
            ->whereNull('ended_at')
            ->when($eligible_detail_ids->isNotEmpty(), function ($query) use ($eligible_detail_ids) {
                $query->whereNotIn('payment_detail_id', $eligible_detail_ids);
            });

        (clone $query)
            ->where('started_at', '>', $occurred_at)
            ->update([
                'ended_at' => DB::raw('started_at'),
            ]);

        $query
            ->where('started_at', '<=', $occurred_at)
            ->update([
                'ended_at' => $occurred_at,
            ]);
    }

    private function openEligibleMissingPeriods(Collection $eligible_detail_ids, Collection $open_detail_ids, int $user_id, Carbon $occurred_at): void
    {
        $rows = $eligible_detail_ids
            ->diff($open_detail_ids)
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
