<?php

namespace App\Observers;

use App\Models\User;
use App\Models\UserMeta;

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
        if ($user->wasChanged('banned_at') && $user->banned_at) {
            $user->updateQuietly([
                'is_online' => false,
            ]);
        }
        if ($user->wasChanged('stop_traffic') && $user->stop_traffic) {
            $user->updateQuietly([
                'is_online' => false,
            ]);
        }

        if ($user->wasChanged('is_vip')) {
            if ($user->is_vip) {
                services()->paymentDetail()->restoreVipLimitsForUser($user);
            } else {
                services()->paymentDetail()->resetVipLimitsForUser($user);
            }
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
