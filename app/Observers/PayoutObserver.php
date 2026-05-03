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
            $callbackRevision = null;

            if ($payout->api_version === 2) {
                $callbackRevision = $payout->callback_payload_revision + 1;

                $payout->forceFill(['callback_payload_revision' => $callbackRevision])->saveQuietly();
            }

            SendPayoutCallbackJob::dispatch($payout, $callbackRevision);
        }
    }
}



