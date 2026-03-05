<?php

namespace App\Jobs;

use App\Enums\BalanceType;
use App\Enums\PayoutOperationType;
use App\Enums\PayoutStatus;
use App\Enums\TransactionType;
use App\Exceptions\PayoutException;
use App\Models\Payout\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreditPayoutToTraderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 10;

    public function __construct(
        private Payout $payout,
    ) {
        $this->afterCommit();
        $this->onQueue('payout');
    }

    public function handle(): void
    {
        $payout = $this->payout->fresh();

        if (! $payout || ! $payout->trader_id) {
            return;
        }

        if (! $payout->status->equals(PayoutStatus::SENT)) {
            return;
        }

        if ($payout->status->equals(PayoutStatus::COMPLETED)) {
            return;
        }

        // Если указан hold_until и он ещё не наступил, просто подождём следующую попытку / ручной перезапуск
        if ($payout->hold_until && $payout->hold_until->isFuture()) {
            $this->release($payout->hold_until->diffInSeconds(now()) ?: 60);

            return;
        }

        try {
            services()->payout()->completeAndCredit($payout);
        } catch (PayoutException) {
            return;
        }
    }
}


