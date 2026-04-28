<?php

declare(strict_types=1);

namespace App\Services\ProviderLiquidity;

use App\Enums\CascadeDealStatus;
use App\Models\CascadeProvider;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

class ProviderLiquidityDashboardService
{
    /**
     * @return array{
     *     statistics: array{
     *         totalTurnover: string,
     *         totalProfit: string,
     *         conversionRate: string,
     *         successOrderCount: int
     *     },
     *     incomeChart: array{labels: array<int, string>, data: array<int, float>},
     *     turnoverChart: array{labels: array<int, string>, data: array<int, float>},
     *     conversionChart: array{labels: array<int, string>, data: array<int, float>},
     *     ordersChart: array{labels: array<int, string>, data: array<int, int>},
     *     averageCheckChart: array{labels: array<int, string>, data: array<int, float>},
     *     selectedPeriodPreset: string,
     *     selectedDateFrom: string,
     *     selectedDateTo: string
     * }
     */
    public function buildMainPageProps(Request $request): array
    {
        $provider = $this->resolveProvider($request);
        $provider?->load('user.wallet');

        $period = $this->resolvePeriod(
            $request,
            $provider ? $provider->deals()->min('created_at') : null,
        );

        $statistics = $this->buildStatistics($provider, $period['startDate'], $period['endDate']);
        $charts = $this->buildCharts($provider, $period['startDate'], $period['endDate']);

        return [
            'statistics' => $statistics,
            'incomeChart' => $charts['incomeChart'],
            'turnoverChart' => $charts['turnoverChart'],
            'conversionChart' => $charts['conversionChart'],
            'ordersChart' => $charts['ordersChart'],
            'averageCheckChart' => $charts['averageCheckChart'],
            'selectedPeriodPreset' => $period['selectedPeriodPreset'],
            'selectedDateFrom' => $period['selectedDateFrom'],
            'selectedDateTo' => $period['selectedDateTo'],
        ];
    }

    /**
     * Интеграция каскада для зоны Provider Liquidity: по пользователю, привязанному к записи
     * cascade_providers.user_id. Идентификатор провайдера из запроса не используется.
     * Для роли Super Admin при обходе этой зоны возвращается первая интеграция с непустым user_id (поведение только для админов).
     */
    public function resolveProvider(Request $request): ?CascadeProvider
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        if ($user->hasRole('Super Admin')) {
            return CascadeProvider::query()->whereNotNull('user_id')->first();
        }

        return CascadeProvider::query()
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * @return array{
     *     selectedPeriodPreset:string,
     *     selectedDateFrom:string,
     *     selectedDateTo:string,
     *     startDate:Carbon,
     *     endDate:Carbon
     * }
     */
    private function resolvePeriod(Request $request, mixed $minimumDate): array
    {
        $selectedPeriodPreset = (string) $request->input('period', 'month');
        $selectedDateFrom = (string) $request->input('date_from', '');
        $selectedDateTo = (string) $request->input('date_to', '');

        $now = now();
        $startDate = $now->copy()->startOfMonth();
        $endDate = $now->copy()->endOfMonth();

        if ($selectedPeriodPreset === 'today') {
            $startDate = $now->copy()->startOfDay();
            $endDate = $now->copy()->endOfDay();
        } elseif ($selectedPeriodPreset === 'week') {
            $startDate = $now->copy()->startOfWeek();
            $endDate = $now->copy()->endOfWeek();
        } elseif ($selectedPeriodPreset === 'month') {
            $startDate = $now->copy()->startOfMonth();
            $endDate = $now->copy()->endOfMonth();
        } elseif ($selectedPeriodPreset === 'all') {
            $minimalDate = $minimumDate ? Carbon::parse((string) $minimumDate) : $now->copy();
            $startDate = $minimalDate->copy()->startOfDay();
            $endDate = $now->copy()->endOfDay();
        } elseif ($selectedPeriodPreset === 'custom') {
            $parsedFrom = $this->parseDate($selectedDateFrom)?->startOfDay();
            $parsedTo = $this->parseDate($selectedDateTo)?->endOfDay();

            if ($parsedFrom && $parsedTo) {
                $startDate = $parsedFrom;
                $endDate = $parsedTo;
            } else {
                $selectedPeriodPreset = 'month';
                $startDate = $now->copy()->startOfMonth();
                $endDate = $now->copy()->endOfMonth();
                $selectedDateFrom = '';
                $selectedDateTo = '';
            }
        } else {
            $selectedPeriodPreset = 'month';
            $startDate = $now->copy()->startOfMonth();
            $endDate = $now->copy()->endOfMonth();
        }

        if ($startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        if ($selectedPeriodPreset !== 'custom') {
            $selectedDateFrom = $startDate->toDateString();
            $selectedDateTo = $endDate->toDateString();
        }

        return [
            'selectedPeriodPreset' => $selectedPeriodPreset,
            'selectedDateFrom' => $selectedDateFrom,
            'selectedDateTo' => $selectedDateTo,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
    }

    /**
     * @return array{totalTurnover:string,totalProfit:string,conversionRate:string,successOrderCount:int}
     */
    private function buildStatistics(?CascadeProvider $provider, Carbon $startDate, Carbon $endDate): array
    {
        if (! $provider) {
            return [
                'totalTurnover' => '0.00',
                'totalProfit' => '0.00',
                'conversionRate' => '0%',
                'successOrderCount' => 0,
            ];
        }

        $baseQuery = $this->dealsForPeriod($provider, $startDate, $endDate);
        $successDeals = (clone $baseQuery)->where('status', CascadeDealStatus::SUCCESS);
        $failedDeals = (clone $baseQuery)->where('status', CascadeDealStatus::FAIL);

        $successDealCount = $successDeals->count();
        $failedDealCount = $failedDeals->count();
        $totalDealsCount = $successDealCount + $failedDealCount;
        $conversionRate = $totalDealsCount > 0
            ? round(($successDealCount / $totalDealsCount) * 100, 2)
            : 0;

        $totalTurnoverUnits = (int) ((clone $successDeals)->sum('usdt_amount') ?? 0);
        $totalProfitUnits = (int) ((clone $successDeals)->sum('debit') ?? 0);

        return [
            'totalTurnover' => number_format($totalTurnoverUnits / 100, 2, '.', ''),
            'totalProfit' => number_format($totalProfitUnits / 100, 2, '.', ''),
            'conversionRate' => "{$conversionRate}%",
            'successOrderCount' => $successDealCount,
        ];
    }

    /**
     * @return array{
     *     incomeChart: array{labels: array<int, string>, data: array<int, float>},
     *     turnoverChart: array{labels: array<int, string>, data: array<int, float>},
     *     conversionChart: array{labels: array<int, string>, data: array<int, float>},
     *     ordersChart: array{labels: array<int, string>, data: array<int, int>},
     *     averageCheckChart: array{labels: array<int, string>, data: array<int, float>}
     * }
     */
    private function buildCharts(?CascadeProvider $provider, Carbon $startDate, Carbon $endDate): array
    {
        $empty = [
            'incomeChart' => ['labels' => [], 'data' => []],
            'turnoverChart' => ['labels' => [], 'data' => []],
            'conversionChart' => ['labels' => [], 'data' => []],
            'ordersChart' => ['labels' => [], 'data' => []],
            'averageCheckChart' => ['labels' => [], 'data' => []],
        ];

        if (! $provider) {
            return $empty;
        }

        $isHourly = $startDate->isSameDay($endDate);
        $bucketSql = $isHourly
            ? "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')"
            : 'DATE(created_at)';

        $baseQuery = $this->dealsForPeriod($provider, $startDate, $endDate);
        $successByBucket = (clone $baseQuery)
            ->where('status', CascadeDealStatus::SUCCESS)
            ->selectRaw("{$bucketSql} as bucket_key, COUNT(*) as count")
            ->groupBy('bucket_key')
            ->pluck('count', 'bucket_key');
        $failedByBucket = (clone $baseQuery)
            ->where('status', CascadeDealStatus::FAIL)
            ->selectRaw("{$bucketSql} as bucket_key, COUNT(*) as count")
            ->groupBy('bucket_key')
            ->pluck('count', 'bucket_key');
        $turnoverByBucket = (clone $baseQuery)
            ->where('status', CascadeDealStatus::SUCCESS)
            ->selectRaw("{$bucketSql} as bucket_key, SUM(usdt_amount) as total_turnover")
            ->groupBy('bucket_key')
            ->pluck('total_turnover', 'bucket_key');
        $incomeByBucket = (clone $baseQuery)
            ->where('status', CascadeDealStatus::SUCCESS)
            ->selectRaw("{$bucketSql} as bucket_key, SUM(debit) as total_income")
            ->groupBy('bucket_key')
            ->pluck('total_income', 'bucket_key');
        $totalAmountByBucket = (clone $baseQuery)
            ->whereIn('status', [CascadeDealStatus::SUCCESS, CascadeDealStatus::FAIL])
            ->selectRaw("{$bucketSql} as bucket_key, SUM(usdt_amount) as total_amount")
            ->groupBy('bucket_key')
            ->pluck('total_amount', 'bucket_key');

        $labels = [];
        $turnoverData = [];
        $incomeData = [];
        $conversionData = [];
        $ordersData = [];
        $averageCheckData = [];

        $currentBucketDate = $startDate->copy();
        while ($currentBucketDate->lte($endDate)) {
            $bucketKey = $isHourly
                ? $currentBucketDate->format('Y-m-d H:00:00')
                : $currentBucketDate->toDateString();
            $successCount = (int) ($successByBucket[$bucketKey] ?? 0);
            $failedCount = (int) ($failedByBucket[$bucketKey] ?? 0);
            $totalCount = $successCount + $failedCount;
            $turnoverUnits = (int) ($turnoverByBucket[$bucketKey] ?? 0);
            $incomeUnits = (int) ($incomeByBucket[$bucketKey] ?? 0);
            $totalAmountUnits = (int) ($totalAmountByBucket[$bucketKey] ?? 0);

            $labels[] = $isHourly
                ? $currentBucketDate->format('H:i')
                : $currentBucketDate->format('d.m');
            $turnoverData[] = round($turnoverUnits / 100, 2);
            $incomeData[] = round($incomeUnits / 100, 2);
            $conversionData[] = $totalCount > 0
                ? round(($successCount / $totalCount) * 100, 2)
                : 0;
            $ordersData[] = $successCount;
            $averageCheckData[] = $totalCount > 0
                ? round(($totalAmountUnits / 100) / $totalCount, 2)
                : 0;

            if ($isHourly) {
                $currentBucketDate->addHour();
            } else {
                $currentBucketDate->addDay();
            }
        }

        return [
            'incomeChart' => ['labels' => $labels, 'data' => $incomeData],
            'turnoverChart' => ['labels' => $labels, 'data' => $turnoverData],
            'conversionChart' => ['labels' => $labels, 'data' => $conversionData],
            'ordersChart' => ['labels' => $labels, 'data' => $ordersData],
            'averageCheckChart' => ['labels' => $labels, 'data' => $averageCheckData],
        ];
    }

    private function dealsForPeriod(CascadeProvider $provider, Carbon $startDate, Carbon $endDate): HasMany
    {
        return $provider->deals()->whereBetween('created_at', [$startDate, $endDate]);
    }

    private function parseDate(string $date): ?Carbon
    {
        if ($date === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date);
        } catch (\Throwable) {
            return null;
        }
    }
}
