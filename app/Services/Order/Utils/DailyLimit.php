<?php

namespace App\Services\Order\Utils;

use App\Models\PaymentDetail;
use App\Services\Money\Money;
use App\Utils\Transaction;
use Carbon\Carbon;

class DailyLimit
{
    public static function increment(int $paymentDetailID, Money $amount, Carbon $day): void
    {
        if (! $day->isToday()) {
            return;
        }

        Transaction::run(function () use ($paymentDetailID, $amount) {
            $paymentDetail = PaymentDetail::where('id', $paymentDetailID)->lockForUpdate()->first();

            $current_daily_limit = $paymentDetail
                ->current_daily_limit
                ->add($amount);

            $paymentDetail->update([
                'current_daily_limit' => $current_daily_limit
            ]);
        });
    }

    public static function decrement(int $paymentDetailID, Money $amount, Carbon $day): void
    {
        if (! $day->isToday()) {
            return;
        }
        
        Transaction::run(function () use ($paymentDetailID, $amount) {
            $paymentDetail = PaymentDetail::where('id', $paymentDetailID)->lockForUpdate()->first();

            $current_daily_limit = $paymentDetail
                ->current_daily_limit
                ->sub($amount);

            $paymentDetail->update([
                'current_daily_limit' => $current_daily_limit
            ]);
        });
    }
}
