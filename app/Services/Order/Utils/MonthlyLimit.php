<?php

namespace App\Services\Order\Utils;

use App\Models\PaymentDetail;
use App\Services\Money\Money;
use App\Utils\Transaction;
use Carbon\Carbon;

class MonthlyLimit
{
    public static function increment(int $paymentDetailID, Money $amount, Carbon $day): void
    {
        Transaction::run(function () use ($paymentDetailID, $amount, $day) {
            $paymentDetail = PaymentDetail::where('id', $paymentDetailID)->lockForUpdate()->first();

            if (! self::mustApplyForDay($paymentDetail, $day)) {
                return;
            }

            $currentMonthlyLimit = $paymentDetail
                ->current_monthly_limit
                ->add($amount);

            $paymentDetail->update([
                'current_monthly_limit' => $currentMonthlyLimit,
            ]);
        });
    }

    public static function decrement(int $paymentDetailID, Money $amount, Carbon $day): void
    {
        Transaction::run(function () use ($paymentDetailID, $amount, $day) {
            $paymentDetail = PaymentDetail::where('id', $paymentDetailID)->lockForUpdate()->first();

            if (! self::mustApplyForDay($paymentDetail, $day)) {
                return;
            }

            $currentMonthlyLimit = $paymentDetail
                ->current_monthly_limit
                ->sub($amount);

            $paymentDetail->update([
                'current_monthly_limit' => $currentMonthlyLimit,
            ]);
        });
    }

    private static function mustApplyForDay(PaymentDetail $paymentDetail, Carbon $day): bool
    {
        $monthlyLimit = $paymentDetail->monthly_limit;
        $resetDay = $paymentDetail->monthly_limit_reset_day;

        if ($monthlyLimit === null || ! $monthlyLimit->greaterThanZero() || $resetDay === null) {
            return false;
        }

        $now = now();
        $periodStart = self::resolveCurrentPeriodStart((int) $resetDay, $now);

        return $day->greaterThanOrEqualTo($periodStart);
    }

    public static function resolveCurrentPeriodStart(int $resetDay, Carbon $now): Carbon
    {
        $currentMonthStartDay = min($resetDay, $now->daysInMonth);
        $currentMonthResetDate = $now->copy()->startOfMonth()->day($currentMonthStartDay)->startOfDay();

        if ($now->greaterThanOrEqualTo($currentMonthResetDate)) {
            return $currentMonthResetDate;
        }

        $previousMonth = $now->copy()->subMonthNoOverflow();
        $previousMonthStartDay = min($resetDay, $previousMonth->daysInMonth);

        return $previousMonth->startOfMonth()->day($previousMonthStartDay)->startOfDay();
    }
}
