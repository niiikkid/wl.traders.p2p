<?php

namespace App\Observers;

use App\Jobs\SendPayoutCallbackJob;
use App\Models\Payout\Payout;

class PayoutObserver
{
    public $afterCommit = true;

    public function updated(Payout $payout): void
    {
        if ($payout->wasChanged('status') || $payout->isDirty('status')) {
            SendPayoutCallbackJob::dispatch($payout);
        }
    }
}



