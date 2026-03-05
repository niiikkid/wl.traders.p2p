<?php

namespace App\Listeners;

use App\Events\OrderSucceeded;

class UpdateTempVipProgressListener
{
    public function handle(OrderSucceeded $event): void
    {
        if (! services()->settings()->isTempVipEnabled()) {
            return;
        }

        $paymentDetail = $event->order->paymentDetail;
        $user = $paymentDetail?->user;

        if (! $user || $user->is_vip) {
            return;
        }

        if ($user->temp_vip_active_until && now()->lt($user->temp_vip_active_until)) {
            return;
        }

        $progress = $user->getTempVipProgressData();

        if ($progress['can_activate'] ?? false) {
            $user->updateQuietly([
                'temp_vip_can_activate' => true,
            ]);
        }
    }
}

