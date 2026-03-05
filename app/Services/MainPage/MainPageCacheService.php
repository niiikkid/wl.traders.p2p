<?php

namespace App\Services\MainPage;

use App\Contracts\MainPageCacheServiceContract;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class MainPageCacheService implements MainPageCacheServiceContract
{
    public function __construct(
        private readonly MainPageStatsService $statsService,
    ) {
    }

    public function rememberMerchant(User $user): array
    {
        return Cache::remember(
            $this->merchantCacheKey($user->id),
            $this->ttl(),
            fn () => $this->statsService->buildMerchantStats($user)
        );
    }

    public function rememberTrader(User $user): array
    {
        return Cache::remember(
            $this->traderCacheKey($user->id),
            $this->ttl(),
            fn () => $this->statsService->buildTraderStats($user)
        );
    }

    public function rememberLeader(User $user): array
    {
        return Cache::remember(
            $this->leaderCacheKey($user->id),
            $this->ttl(),
            fn () => $this->statsService->buildLeaderStats($user)
        );
    }

    public function rememberAdmin(User $user, ?int $merchantId = null): array
    {
        return Cache::remember(
            $this->adminCacheKey($user->id, $merchantId),
            $this->ttl(),
            fn () => $this->statsService->buildAdminStats($user, $merchantId)
        );
    }

    public function cacheMerchant(User $user): array
    {
        $stats = $this->statsService->buildMerchantStats($user);
        Cache::put($this->merchantCacheKey($user->id), $stats, $this->ttl());

        return $stats;
    }

    public function cacheTrader(User $user): array
    {
        $stats = $this->statsService->buildTraderStats($user);
        Cache::put($this->traderCacheKey($user->id), $stats, $this->ttl());

        return $stats;
    }

    public function cacheLeader(User $user): array
    {
        $stats = $this->statsService->buildLeaderStats($user);
        Cache::put($this->leaderCacheKey($user->id), $stats, $this->ttl());

        return $stats;
    }

    public function cacheAdmin(User $user, ?int $merchantId = null): array
    {
        $stats = $this->statsService->buildAdminStats($user, $merchantId);
        Cache::put($this->adminCacheKey($user->id, $merchantId), $stats, $this->ttl());

        return $stats;
    }

    public function cacheByViewMode(User $user, string $viewMode, ?int $merchantId = null): array
    {
        return match ($viewMode) {
            'merchant' => $this->cacheMerchant($user),
            'trader' => $this->cacheTrader($user),
            'leader' => $this->cacheLeader($user),
            'admin' => $this->cacheAdmin($user, $merchantId),
            default => throw new InvalidArgumentException("Unsupported view mode: {$viewMode}"),
        };
    }

    private function merchantCacheKey(int $userId): string
    {
        return "merchant-main-page-stats-{$userId}";
    }

    private function traderCacheKey(int $userId): string
    {
        return "trader-main-page-stats-{$userId}";
    }

    private function leaderCacheKey(int $userId): string
    {
        return "team-leader-main-page-stats-{$userId}";
    }

    private function adminCacheKey(int $userId, ?int $merchantId): string
    {
        return "admin-main-page-stats-{$userId}-{$merchantId}";
    }

    private function ttl(): DateTimeInterface
    {
        return now()->addDay();
    }
}

