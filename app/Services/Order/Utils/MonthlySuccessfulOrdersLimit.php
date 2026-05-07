<?php

namespace App\Services\Order\Utils;

use App\Models\PaymentDetail;
use App\Utils\Transaction;
use Carbon\Carbon;

class MonthlySuccessfulOrdersLimit
{
    public static function increment(int $paymentDetailID, Carbon $day): void
    {
        Transaction::run(function () use ($paymentDetailID, $day) {
            $paymentDetail = PaymentDetail::query()->where('id', $paymentDetailID)->lockForUpdate()->first();

            if (! self::mustApplyForDay($paymentDetail, $day)) {
                return;
            }

            $currentCount = (int) $paymentDetail->current_monthly_successful_orders_count;

            $paymentDetail->update([
                'current_monthly_successful_orders_count' => $currentCount + 1,
            ]);
        });
    }

    public static function decrement(int $paymentDetailID, Carbon $day): void
    {
        Transaction::run(function () use ($paymentDetailID, $day) {
            $paymentDetail = PaymentDetail::query()->where('id', $paymentDetailID)->lockForUpdate()->first();

            if (! self::mustApplyForDay($paymentDetail, $day)) {
                return;
            }

            $currentCount = (int) $paymentDetail->current_monthly_successful_orders_count;

            $paymentDetail->update([
                'current_monthly_successful_orders_count' => max(0, $currentCount - 1),
            ]);
        });
    }

    private static function mustApplyForDay(PaymentDetail $paymentDetail, Carbon $day): bool
    {
        $monthlyOrdersLimit = $paymentDetail->monthly_successful_orders_limit;
        $resetDay = $paymentDetail->monthly_limit_reset_day;

        if ($monthlyOrdersLimit === null || $resetDay === null) {
            return false;
        }

        $now = now();
        $periodStart = MonthlyLimit::resolveCurrentPeriodStart((int) $resetDay, $now);

        return $day->greaterThanOrEqualTo($periodStart);
    }
}
