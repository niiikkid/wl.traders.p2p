<?php

namespace App\Jobs;

use App\Models\Payout\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Redis;

class SendPayoutCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 8;
    public int $timeout = 10;

    private const LOCK_TTL = 120;

    public function __construct(private Payout $payout)
    {
        $this->onQueue('callback');
        $this->afterCommit();
    }

    public function handle(): void
    {
        $lockKey = $this->getLockKey();

        if ($this->acquireLock($lockKey)) {
            try {
                services()->callback()->sendForPayout($this->payout);
            } finally {
                $this->releaseLock($lockKey);
            }
        } else {
            $this->release(10);
        }
    }

    private function getLockKey(): string
    {
        return 'payout_callback_lock:' . $this->payout->id;
    }

    private function acquireLock(string $key): bool
    {
        return (bool) Redis::set($key, 'locked', 'NX', 'EX', self::LOCK_TTL);
    }

    private function releaseLock(string $key): void
    {
        Redis::del($key);
    }

    public function backoff(): array
    {
        return [10, 60, 120, 240, 480, 1800, 3600, 7200];
    }
}


