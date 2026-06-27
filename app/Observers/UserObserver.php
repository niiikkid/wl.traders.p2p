<?php

namespace App\Observers;

use App\Models\User;
use App\Models\UserMeta;
use App\Services\PaymentDetail\PaymentDetailEnabledPeriodService;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        UserMeta::create([
            'user_id' => $user->id,
        ]);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        $shouldSyncDetailEnabledPeriods = false;

        if ($user->wasChanged('banned_at') && $user->banned_at) {
            $user->updateQuietly([
                'is_online' => false,
            ]);
            $shouldSyncDetailEnabledPeriods = true;
        }
        if ($user->wasChanged('stop_traffic') && $user->stop_traffic) {
            $user->updateQuietly([
                'is_online' => false,
            ]);
            $shouldSyncDetailEnabledPeriods = true;
        }

        if ($user->wasChanged('is_online') || $user->wasChanged('stop_traffic')) {
            $shouldSyncDetailEnabledPeriods = true;
        }

        if ($user->wasChanged('can_set_order_amount_limits')) {
            if ($user->can_set_order_amount_limits) {
                services()->paymentDetail()->restoreOrderAmountLimitsForUser($user);
            } else {
                services()->paymentDetail()->resetOrderAmountLimitsForUser($user);
            }
        }

        if ($shouldSyncDetailEnabledPeriods) {
            app(PaymentDetailEnabledPeriodService::class)->syncForUser($user);
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
