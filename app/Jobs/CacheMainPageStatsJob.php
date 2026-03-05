<?php

namespace App\Jobs;

use App\Contracts\MainPageCacheServiceContract;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class CacheMainPageStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'main-page-cache';

    public int $timeout = 120;
    public int $tries = 1;

    public function __construct(
        private readonly int $userId,
        private readonly string $viewMode,
        private readonly ?int $merchantId = null,
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function handle(MainPageCacheServiceContract $cacheService): void
    {
        $user = User::query()->find($this->userId);

        if (!$user) {
            return;
        }

        $cacheService->cacheByViewMode($user, $this->viewMode, $this->merchantId);
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}

