<?php

declare(strict_types=1);

namespace App\Services\PaymentDetail;

use App\Models\PaymentDetail;
use App\Services\Money\Money;
use App\Utils\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class PaymentDetailLimitResetService
{
    public function resetDailyLimitsForPaymentDetail(PaymentDetail $paymentDetail): void
    {
        Transaction::run(function () use ($paymentDetail) {
            $lockedPaymentDetail = PaymentDetail::query()
                ->where('id', $paymentDetail->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedPaymentDetail->update([
                'current_daily_limit' => Money::zero($lockedPaymentDetail->currency->getCode()),
                'current_daily_successful_orders_count' => 0,
            ]);
        });
    }

    public function resetMonthlyLimitsForPaymentDetail(PaymentDetail $paymentDetail): void
    {
        Transaction::run(function () use ($paymentDetail) {
            $lockedPaymentDetail = PaymentDetail::query()
                ->where('id', $paymentDetail->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedPaymentDetail->update([
                'current_monthly_limit' => Money::zero($lockedPaymentDetail->currency->getCode()),
                'current_monthly_successful_orders_count' => 0,
            ]);
        });
    }

    public function resetDailyLimitsForAll(): void
    {
        PaymentDetail::query()->update([
            'current_daily_limit' => 0,
            'current_daily_successful_orders_count' => 0,
        ]);
    }

    public function resetMonthlyLimitsDueToday(?Carbon $today = null): void
    {
        $today ??= Carbon::today();

        PaymentDetail::query()
            ->whereNotNull('monthly_limit_reset_day')
            ->where(function (Builder $query) use ($today) {
                $query->where('monthly_limit_reset_day', $today->day);

                if ($today->isLastOfMonth()) {
                    $query->orWhere('monthly_limit_reset_day', '>', $today->daysInMonth);
                }
            })
            ->update([
                'current_monthly_limit' => 0,
                'current_monthly_successful_orders_count' => 0,
            ]);
    }
}
