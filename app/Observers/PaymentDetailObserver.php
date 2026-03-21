<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\PaymentDetail;
use App\Services\PaymentDetail\PaymentDetailEnabledPeriodService;

class PaymentDetailObserver
{
    public function created(PaymentDetail $payment_detail): void
    {
        app(PaymentDetailEnabledPeriodService::class)->syncForPaymentDetail($payment_detail);
    }

    public function updated(PaymentDetail $payment_detail): void
    {
        if (
            $payment_detail->wasChanged('is_active')
            || $payment_detail->wasChanged('archived_at')
            || $payment_detail->wasChanged('user_id')
        ) {
            app(PaymentDetailEnabledPeriodService::class)->syncForPaymentDetail($payment_detail);
        }
    }
}
