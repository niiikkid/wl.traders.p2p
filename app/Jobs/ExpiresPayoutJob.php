<?php

namespace App\Jobs;

use App\Enums\PayoutStatus;
use App\Exceptions\PayoutException;
use App\Models\Payout\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpiresPayoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private Payout $payout,
    )
    {
        $this->afterCommit();
        $this->onQueue('payout');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $payout = $this->payout->fresh();

        if (! $payout) {
            return;
        }

        if (! $payout->expires_at || $payout->expires_at->isFuture()) {
            return;
        }

        if (! $payout->status->equals(PayoutStatus::OPEN) || $payout->trader_id !== null) {
            return;
        }

        try {
            services()->payout()->cancel($payout);
        } catch (PayoutException) {
            // Если по каким-то причинам отменить не удалось (статус изменился и т.п.) – просто пропускаем
            return;
        }
    }

    public function backoff(): array
    {
        return [30, 60, 180];
    }
}


