<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Exceptions\OrderSmsLogException;
use App\Models\SmsLog;
use Illuminate\Support\Facades\DB;

class SmsLogRejectService
{
    public function reject(SmsLog $smsLog): SmsLog
    {
        return DB::transaction(function () use ($smsLog): SmsLog {
            $lockedSmsLog = SmsLog::query()
                ->whereKey($smsLog->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSmsLog->order_id !== null) {
                throw OrderSmsLogException::smsLogAlreadyLinked();
            }

            if ($lockedSmsLog->rejected_at !== null) {
                throw OrderSmsLogException::smsLogAlreadyRejected();
            }

            $lockedSmsLog->update([
                'rejected_at' => now(),
            ]);

            return $lockedSmsLog->fresh();
        });
    }
}
