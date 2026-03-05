<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TempVipExpireJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 10;

    public function __construct(
        private User $user,
    ) {
        $this->afterCommit();
        $this->onQueue('order');
    }

    public function handle(): void
    {
        if (! $this->user->temp_vip_active_until) {
            return;
        }

        if (now()->lt($this->user->temp_vip_active_until)) {
            self::dispatch($this->user)->delay($this->user->temp_vip_active_until);
            return;
        }

        $this->user->updateQuietly([
            'temp_vip_active_until' => null,
            'temp_vip_can_activate' => false,
            'temp_vip_progress_start_at' => now(),
        ]);

        services()->paymentDetail()->resetVipLimitsForUser($this->user);
    }
}

