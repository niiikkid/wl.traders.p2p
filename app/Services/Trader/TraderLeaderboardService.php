<?php

declare(strict_types=1);

namespace App\Services\Trader;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class TraderLeaderboardService
{
    /**
     * @return array{
     *   period_start:string,
     *   period_end:string,
     *   reset_at:string,
     *   generated_at:string,
     *   top:array<int, array{
     *      rank:int,
     *      trader_id:int,
     *      nickname:string,
     *      email:string,
     *      is_name_hidden:bool,
     *      is_online:bool,
     *      operations_count:int,
     *      avg_processing_seconds:int,
     *      avg_processing_human:string
     *   }>,
     *   current_trader:array{
     *      rank:int,
     *      trader_id:int,
     *      nickname:string,
     *      email:string,
     *      is_name_hidden:bool,
     *      is_online:bool,
     *      operations_count:int,
     *      avg_processing_seconds:int,
     *      avg_processing_human:string
     *   }|null
     * }
     */
    public function getWeeklyTop(?string $currency, int $currentTraderId, int $limit = 10, bool $applyPrivacy = true, int $weekOffset = 0): array
    {
        $payload = $this->getWeeklyLeaderboardPayload($currency, $weekOffset);
        $rows = $this->applyViewerPrivacy($payload['rows'], $applyPrivacy, $currentTraderId);

        $currentTrader = collect($rows)->firstWhere('trader_id', $currentTraderId);

        return [
            'period_start' => $payload['period_start'],
            'period_end' => $payload['period_end'],
            'reset_at' => $payload['reset_at'],
            'generated_at' => $payload['generated_at'],
            'top' => array_slice($rows, 0, $limit),
            'current_trader' => $currentTrader ?: null,
        ];
    }

    /**
     * @return array{
     *   period_start:string,
     *   period_end:string,
     *   reset_at:string,
     *   generated_at:string,
     *   top:array<int, array{
     *      rank:int,
     *      trader_id:int,
     *      nickname:string,
     *      email:string,
     *      is_name_hidden:bool,
     *      is_online:bool,
     *      operations_count:int,
     *      total_amount_units:int,
     *      total_amount_human:string
     *   }>,
     *   current_trader:array{
     *      rank:int,
     *      trader_id:int,
     *      nickname:string,
     *      email:string,
     *      is_name_hidden:bool,
     *      is_online:bool,
     *      operations_count:int,
     *      total_amount_units:int,
     *      total_amount_human:string
     *   }|null
     * }
     */
    public function getMonthlyTop(?string $currency, int $currentTraderId, int $limit = 10, bool $applyPrivacy = true, int $monthOffset = 0): array
    {
        $payload = $this->getMonthlyLeaderboardPayload($currency, $monthOffset);
        $rows = $this->applyViewerPrivacy($payload['rows'], $applyPrivacy, $currentTraderId);

        $currentTrader = collect($rows)->firstWhere('trader_id', $currentTraderId);

        return [
            'period_start' => $payload['period_start'],
            'period_end' => $payload['period_end'],
            'reset_at' => $payload['reset_at'],
            'generated_at' => $payload['generated_at'],
            'top' => array_slice($rows, 0, $limit),
            'current_trader' => $currentTrader ?: null,
        ];
    }

    public function getTraderWeeklyRank(int $traderId, ?string $currency = null): ?int
    {
        $payload = $this->getWeeklyLeaderboardPayload($currency, 0);
        $row = collect($payload['rows'])->firstWhere('trader_id', $traderId);

        return $row['rank'] ?? null;
    }

    /**
     * @return array{
     *   period_start:string,
     *   period_end:string,
     *   reset_at:string,
     *   generated_at:string,
     *   rows:array<int, array{
     *      rank:int,
     *      trader_id:int,
     *      nickname:string,
     *      email:string,
     *      is_name_hidden:bool,
     *      is_online:bool,
     *      operations_count:int,
     *      avg_processing_seconds:int,
     *      avg_processing_human:string
     *   }>
     * }
     */
    private function getWeeklyLeaderboardPayload(?string $currency, int $weekOffset = 0): array
    {
        $resolvedWeekOffset = max(0, $weekOffset);
        $currencyCode = $currency !== null && trim($currency) !== ''
            ? strtolower($currency)
            : 'all';
        $cacheKey = sprintf('trader_weekly_leaderboard:%s:%d', $currencyCode, $resolvedWeekOffset);
        $now = CarbonImmutable::now();
        $baseWeekDate = $now->subWeeks($resolvedWeekOffset);
        $periodStart = $baseWeekDate->startOfWeek()->startOfDay();
        $periodEnd = $baseWeekDate->endOfWeek()->endOfDay();
        $resetAt = $periodEnd->addSecond();

        $resolver = function () use ($currencyCode, $now, $periodStart, $periodEnd, $resetAt) {

            $rowsQuery = Order::query()
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->where('status', OrderStatus::SUCCESS)
                ->whereNotNull('trader_id')
                ->selectRaw('trader_id, COUNT(*) as operations_count, AVG(TIMESTAMPDIFF(SECOND, created_at, finished_at)) as avg_processing_seconds')
                ->groupBy('trader_id')
                ->orderByDesc('operations_count')
                ->orderBy('avg_processing_seconds')
                ->orderBy('trader_id');

            if ($currencyCode !== 'all') {
                $rowsQuery->where('currency', $currencyCode);
            }

            $rows = $rowsQuery->get();

            $traderIds = $rows->pluck('trader_id')->filter()->map(fn ($id) => (int) $id)->values();
            $tradersMap = User::query()
                ->whereIn('id', $traderIds)
                ->get(['id', 'name', 'email', 'is_online', 'hide_name_in_trader_top'])
                ->keyBy('id');

            $preparedRows = $rows
                ->values()
                ->map(function ($row, int $index) use ($tradersMap) {
                    $trader = $tradersMap->get((int) $row->trader_id);
                    $avgProcessingSeconds = (int) round((float) ($row->avg_processing_seconds ?? 0));

                    return [
                        'rank' => $index + 1,
                        'trader_id' => (int) $row->trader_id,
                        'nickname' => (string) ($trader?->name ?: $trader?->email ?: ('Трейдер #' . (int) $row->trader_id)),
                        'email' => (string) ($trader?->email ?: '-'),
                        'is_name_hidden' => (bool) ($trader?->hide_name_in_trader_top ?? true),
                        'is_online' => (bool) ($trader?->is_online ?? false),
                        'operations_count' => (int) $row->operations_count,
                        'avg_processing_seconds' => $avgProcessingSeconds,
                        'avg_processing_human' => $this->formatSecondsToHuman($avgProcessingSeconds),
                    ];
                })
                ->all();

            return [
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'reset_at' => $resetAt->toISOString(),
                'generated_at' => $now->toISOString(),
                'rows' => $preparedRows,
            ];
        };

        if (is_local()) {
            return $resolver();
        }

        $cacheTtl = $now->addHour();
        if ($resetAt->lessThan($cacheTtl)) {
            $cacheTtl = $resetAt;
        }

        return Cache::remember($cacheKey, $cacheTtl, $resolver);
    }

    /**
     * @return array{
     *   period_start:string,
     *   period_end:string,
     *   reset_at:string,
     *   generated_at:string,
     *   rows:array<int, array{
     *      rank:int,
     *      trader_id:int,
     *      nickname:string,
     *      email:string,
     *      is_name_hidden:bool,
     *      is_online:bool,
     *      operations_count:int,
     *      total_amount_units:int,
     *      total_amount_human:string
     *   }>
     * }
     */
    private function getMonthlyLeaderboardPayload(?string $currency, int $monthOffset = 0): array
    {
        $resolvedMonthOffset = max(0, $monthOffset);
        $currencyCode = $currency !== null && trim($currency) !== ''
            ? strtolower($currency)
            : 'all';
        $cacheKey = sprintf('trader_monthly_leaderboard:%s:%d', $currencyCode, $resolvedMonthOffset);
        $now = CarbonImmutable::now();
        $baseMonthDate = $now->subMonths($resolvedMonthOffset);
        $periodStart = $baseMonthDate->startOfMonth()->startOfDay();
        $periodEnd = $baseMonthDate->endOfMonth()->endOfDay();
        $resetAt = $periodEnd->addSecond();

        $resolver = function () use ($currencyCode, $now, $periodStart, $periodEnd, $resetAt) {
            $rowsQuery = Order::query()
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->where('status', OrderStatus::SUCCESS)
                ->whereNotNull('trader_id')
                ->selectRaw('trader_id, COUNT(*) as operations_count, SUM(total_profit) as total_amount')
                ->groupBy('trader_id')
                ->orderByDesc('total_amount')
                ->orderByDesc('operations_count')
                ->orderBy('trader_id');

            if ($currencyCode !== 'all') {
                $rowsQuery->where('currency', $currencyCode);
            }

            $rows = $rowsQuery->get();

            $traderIds = $rows->pluck('trader_id')->filter()->map(fn ($id) => (int) $id)->values();
            $tradersMap = User::query()
                ->whereIn('id', $traderIds)
                ->get(['id', 'name', 'email', 'is_online', 'hide_name_in_trader_top'])
                ->keyBy('id');

            $preparedRows = $rows
                ->values()
                ->map(function ($row, int $index) use ($tradersMap) {
                    $trader = $tradersMap->get((int) $row->trader_id);
                    $totalAmountUnits = (int) ($row->total_amount ?? 0);
                    $totalAmountHuman = Money::fromUnits((string) $totalAmountUnits, 'usdt')->toBeauty();

                    return [
                        'rank' => $index + 1,
                        'trader_id' => (int) $row->trader_id,
                        'nickname' => (string) ($trader?->name ?: $trader?->email ?: ('Трейдер #' . (int) $row->trader_id)),
                        'email' => (string) ($trader?->email ?: '-'),
                        'is_name_hidden' => (bool) ($trader?->hide_name_in_trader_top ?? true),
                        'is_online' => (bool) ($trader?->is_online ?? false),
                        'operations_count' => (int) $row->operations_count,
                        'total_amount_units' => $totalAmountUnits,
                        'total_amount_human' => $totalAmountHuman,
                    ];
                })
                ->all();

            return [
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'reset_at' => $resetAt->toISOString(),
                'generated_at' => $now->toISOString(),
                'rows' => $preparedRows,
            ];
        };

        if (is_local()) {
            return $resolver();
        }

        $cacheTtl = $now->addHour();
        if ($resetAt->lessThan($cacheTtl)) {
            $cacheTtl = $resetAt;
        }

        return Cache::remember($cacheKey, $cacheTtl, $resolver);
    }

    private function formatSecondsToHuman(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0 мин';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return sprintf('%d ч %d мин', $hours, $minutes);
        }

        return sprintf('%d мин', $minutes);
    }

    private function resolveNicknameForViewer(array $row, int $currentTraderId): string
    {
        $nickname = (string) ($row['nickname'] ?? '');
        $isNameHidden = (bool) ($row['is_name_hidden'] ?? true);
        $isCurrentTrader = (int) ($row['trader_id'] ?? 0) === $currentTraderId;

        if (! $isNameHidden || $isCurrentTrader) {
            return $nickname;
        }

        return $this->maskNickname($nickname);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function applyViewerPrivacy(array $rows, bool $applyPrivacy, int $currentTraderId): array
    {
        return collect($rows)
            ->map(function (array $row) use ($applyPrivacy, $currentTraderId) {
                $resolvedNickname = $applyPrivacy
                    ? $this->resolveNicknameForViewer($row, $currentTraderId)
                    : (string) $row['nickname'];

                return [
                    ...$row,
                    'nickname' => $resolvedNickname,
                ];
            })
            ->values()
            ->all();
    }

    private function maskNickname(string $nickname): string
    {
        $value = trim($nickname);

        if ($value === '') {
            return '***';
        }

        $visiblePart = mb_substr($value, 0, 3);

        return $visiblePart . '*****';
    }
}
