<?php

declare(strict_types=1);

namespace App\Services\ProviderLiquidity;

use App\Enums\CascadeDealStatus;
use App\Models\CascadeDeal;
use App\Models\CascadeProvider;
use App\Services\Money\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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
        $providers = $this->resolveProviders($request);
        $request->user()?->load('wallet');

        $period = $this->resolvePeriod(
            $request,
            $this->minDealCreatedAtForProviders($providers),
        );

        $statistics = $this->buildStatistics($providers, $period['startDate'], $period['endDate']);
        $charts = $this->buildCharts($providers, $period['startDate'], $period['endDate']);

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
     * Все интеграции каскада для зоны Provider Liquidity: записи cascade_providers с тем же user_id,
     * что у текущего пользователя (в т.ч. при переключении вида «Провайдер» у Super Admin без привязки — пусто).
     * Идентификатор провайдера из запроса не подставляется.
     *
     * @return Collection<int, CascadeProvider>
     */
    public function resolveProviders(Request $request): Collection
    {
        $user = $request->user();

        if (! $user) {
            return collect();
        }

        return CascadeProvider::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * Первая интеграция из {@see resolveProviders} (обратная совместимость).
     */
    public function resolveProvider(Request $request): ?CascadeProvider
    {
        return $this->resolveProviders($request)->first();
    }

    /**
     * @param  Collection<int, CascadeProvider>  $providers
     */
    private function minDealCreatedAtForProviders(Collection $providers): mixed
    {
        if ($providers->isEmpty()) {
            return null;
        }

        return CascadeDeal::query()
            ->whereIn('selected_provider_id', $providers->pluck('id')->all())
            ->min('created_at');
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
     * @param  Collection<int, CascadeProvider>  $providers
     * @return array{totalTurnover:string,totalProfit:string,conversionRate:string,successOrderCount:int}
     */
    private function buildStatistics(Collection $providers, Carbon $startDate, Carbon $endDate): array
    {
        if ($providers->isEmpty()) {
            return [
                'totalTurnover' => '0.00',
                'totalProfit' => '0.00',
                'conversionRate' => '0%',
                'successOrderCount' => 0,
            ];
        }

        $baseQuery = $this->dealsForPeriod($providers, $startDate, $endDate);
        $successDeals = (clone $baseQuery)->where('status', CascadeDealStatus::SUCCESS);
        $failedDeals = (clone $baseQuery)->where('status', CascadeDealStatus::FAIL);

        $successDealCount = $successDeals->count();
        $failedDealCount = $failedDeals->count();
        $totalDealsCount = $successDealCount + $failedDealCount;
        $conversionRate = $totalDealsCount > 0
            ? round(($successDealCount / $totalDealsCount) * 100, 2)
            : 0;

        $totalTurnoverSum = (clone $successDeals)->sum('usdt_amount');
        $totalProfitSum = (clone $successDeals)->sum('debit');

        return [
            'totalTurnover' => $this->moneyFromUsdtSumUnits($totalTurnoverSum)->toBeauty(),
            'totalProfit' => $this->moneyFromUsdtSumUnits($totalProfitSum)->toBeauty(),
            'conversionRate' => "{$conversionRate}%",
            'successOrderCount' => $successDealCount,
        ];
    }

    /**
     * @param  Collection<int, CascadeProvider>  $providers
     * @return array{
     *     incomeChart: array{labels: array<int, string>, data: array<int, float>},
     *     turnoverChart: array{labels: array<int, string>, data: array<int, float>},
     *     conversionChart: array{labels: array<int, string>, data: array<int, float>},
     *     ordersChart: array{labels: array<int, string>, data: array<int, int>},
     *     averageCheckChart: array{labels: array<int, string>, data: array<int, float>}
     * }
     */
    private function buildCharts(Collection $providers, Carbon $startDate, Carbon $endDate): array
    {
        $empty = [
            'incomeChart' => ['labels' => [], 'data' => []],
            'turnoverChart' => ['labels' => [], 'data' => []],
            'conversionChart' => ['labels' => [], 'data' => []],
            'ordersChart' => ['labels' => [], 'data' => []],
            'averageCheckChart' => ['labels' => [], 'data' => []],
        ];

        if ($providers->isEmpty()) {
            return $empty;
        }

        $isHourly = $startDate->isSameDay($endDate);
        $bucketSql = $isHourly
            ? "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')"
            : 'DATE(created_at)';

        $baseQuery = $this->dealsForPeriod($providers, $startDate, $endDate);
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
            $turnoverSum = $turnoverByBucket[$bucketKey] ?? null;
            $incomeSum = $incomeByBucket[$bucketKey] ?? null;
            $totalAmountSum = $totalAmountByBucket[$bucketKey] ?? null;

            $labels[] = $isHourly
                ? $currentBucketDate->format('H:i')
                : $currentBucketDate->format('d.m');
            $turnoverData[] = $this->usdtSumUnitsToChartFloat($turnoverSum);
            $incomeData[] = $this->usdtSumUnitsToChartFloat($incomeSum);
            $conversionData[] = $totalCount > 0
                ? round(($successCount / $totalCount) * 100, 2)
                : 0;
            $ordersData[] = $successCount;
            $averageCheckData[] = $totalCount > 0
                ? round(
                    (float) $this->moneyFromUsdtSumUnits($totalAmountSum)->toPrecision() / $totalCount,
                    2,
                )
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

    /**
     * Значения {@see CascadeDeal::$usdt_amount}, {@see CascadeDeal::$debit} хранятся в минимальных единицах USDT (8 знаков), как в {@see Money}.
     */
    private function moneyFromUsdtSumUnits(mixed $sumUnits): Money
    {
        if ($sumUnits === null || $sumUnits === '') {
            return Money::fromUnits('0', 'usdt');
        }

        return Money::fromUnits((string) $sumUnits, 'usdt');
    }

    private function usdtSumUnitsToChartFloat(mixed $sumUnits): float
    {
        return round((float) $this->moneyFromUsdtSumUnits($sumUnits)->toPrecision(), 2);
    }

    /**
     * @param  Collection<int, CascadeProvider>  $providers
     */
    private function dealsForPeriod(Collection $providers, Carbon $startDate, Carbon $endDate): Builder
    {
        $query = CascadeDeal::query()->whereBetween('created_at', [$startDate, $endDate]);

        if ($providers->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn('selected_provider_id', $providers->pluck('id')->all());
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
