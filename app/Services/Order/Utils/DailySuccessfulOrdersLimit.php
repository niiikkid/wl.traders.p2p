<?php

namespace App\Services\Order\Utils;

use App\Models\PaymentDetail;
use App\Utils\Transaction;
use Carbon\Carbon;

class DailySuccessfulOrdersLimit
{
    public static function increment(int $paymentDetailID, Carbon $day): void
    {
        if (! $day->isToday()) {
            return;
        }

        Transaction::run(function () use ($paymentDetailID) {
            $paymentDetail = PaymentDetail::where('id', $paymentDetailID)->lockForUpdate()->first();

            $currentCount = (int) $paymentDetail->current_daily_successful_orders_count;

            $paymentDetail->update([
                'current_daily_successful_orders_count' => $currentCount + 1,
            ]);
        });
    }

    public static function decrement(int $paymentDetailID, Carbon $day): void
    {
        if (! $day->isToday()) {
            return;
        }

        Transaction::run(function () use ($paymentDetailID) {
            $paymentDetail = PaymentDetail::where('id', $paymentDetailID)->lockForUpdate()->first();

            $currentCount = (int) $paymentDetail->current_daily_successful_orders_count;

            $paymentDetail->update([
                'current_daily_successful_orders_count' => max(0, $currentCount - 1),
            ]);
        });
    }
}
