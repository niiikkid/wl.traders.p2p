<?php

namespace App\Jobs;

use App\DTO\SMS\ShadowSmsLogData;
use App\Services\Sms\ShadowSmsLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RecordShadowSmsLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private ShadowSmsLogData $data,
    ) {
        $this->afterCommit();
        $this->onQueue('sms');
    }

    public function handle(ShadowSmsLogService $shadowSmsLogService): void
    {
        try {
            $shadowSmsLogService->create($this->data);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
