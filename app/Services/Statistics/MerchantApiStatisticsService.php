<?php

namespace App\Services\Statistics;

use App\Contracts\MerchantApiStatisticsServiceContract;
use App\Models\MerchantApiRequestLog;
use App\Models\MerchantApiStatistic;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\PaymentDetail\PaymentDetailVolumeStatisticsService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MerchantApiStatisticsService implements MerchantApiStatisticsServiceContract
{
    private const int AMOUNT_DISTRIBUTION_CACHE_TTL_SECONDS = 60;

    private const int DASHBOARD_STATS_CACHE_TTL_SECONDS = 30;

    private const int REQUESTS_CHART_CACHE_TTL_SECONDS = 60;

    private const int ORDER_CREATE_PROCESSING_CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly PaymentDetailVolumeStatisticsService $volumeStatisticsService,
    ) {}

    /**
     * @return array{
     *     period: string,
     *     currency: string,
     *     currency_symbol: string,
     *     period_options: list<array{value: string, label: string}>,
     *     currency_options: list<string>,
     *     successful_requests_count: int,
     *     all_requests_count: int,
     *     successful_total_amount: string,
     *     all_total_amount: string,
     *     distribution: array{
     *         buckets: list<array{
     *             key: string,
     *             label: string,
     *             successful_count: int,
     *             successful_percent: float,
     *             all_count: int,
     *             all_percent: float
     *         }>,
     *         total_successful: int,
     *         total_all: int
     *     }
     * }
     */
    public function getAmountDistribution(string $currency, string $period): array
    {
        $currency = strtolower($currency);
        $period = $this->volumeStatisticsService->normalizeAmountDistributionPeriod($period);

        return Cache::remember(
            "merchant_api_logs_amount_distribution:{$currency}:{$period}",
            now()->addSeconds(self::AMOUNT_DISTRIBUTION_CACHE_TTL_SECONDS),
            fn (): array => $this->calculateAmountDistribution($currency, $period),
        );
    }

    /**
     * @return array{
     *     period: string,
     *     currency: string,
     *     currency_symbol: string,
     *     period_options: list<array{value: string, label: string}>,
     *     currency_options: list<string>,
     *     successful_requests_count: int,
     *     all_requests_count: int,
     *     successful_total_amount: string,
     *     all_total_amount: string,
     *     distribution: array{
     *         buckets: list<array{
     *             key: string,
     *             label: string,
     *             successful_count: int,
     *             successful_percent: float,
     *             all_count: int,
     *             all_percent: float
     *         }>,
     *         total_successful: int,
     *         total_all: int
     *     }
     * }
     */
    private function calculateAmountDistribution(string $currency, string $period): array
    {
        [$periodStartAt, $periodEndAt] = $this->volumeStatisticsService->resolvePeriodBounds($period, null, null);
        $amountExpression = $this->normalizedAmountExpression();
        $bucketCaseSql = $this->volumeStatisticsService->modalDealAmountBucketCaseSqlForFiatAmount($currency, $amountExpression);
        $currencyModel = Currency::make($currency);

        $logsQuery = MerchantApiRequestLog::query()
            ->tap(fn (Builder $query) => $this->applyOrderRequestTypeFilter($query))
            ->whereRaw('LOWER(currency) = ?', [$currency])
            ->whereNotNull('amount')
            ->where('amount', '!=', '')
            ->when($periodStartAt !== null, fn (Builder $query) => $query->where('created_at', '>=', $periodStartAt))
            ->when($periodEndAt !== null, fn (Builder $query) => $query->where('created_at', '<=', $periodEndAt));

        /** @var Collection<int, object{amount_bucket: string, all_count: int|string, successful_count: int|string}> $rows */
        $rows = (clone $logsQuery)
            ->selectRaw("{$bucketCaseSql} as amount_bucket, COUNT(*) as all_count, SUM(CASE WHEN is_successful = 1 THEN 1 ELSE 0 END) as successful_count")
            ->groupBy('amount_bucket')
            ->get();

        $distribution = $this->formatDualAmountDistribution($rows, $currency);

        $allTotalAmount = (float) ((clone $logsQuery)->selectRaw("COALESCE(SUM({$amountExpression}), 0) as total")->value('total') ?? 0);
        $successfulTotalAmount = (float) ((clone $logsQuery)
            ->where('is_successful', true)
            ->selectRaw("COALESCE(SUM({$amountExpression}), 0) as total")
            ->value('total') ?? 0);

        return [
            'period' => $period,
            'currency' => $currency,
            'currency_symbol' => $currencyModel->getSymbol(),
            'period_options' => PaymentDetailVolumeStatisticsService::AMOUNT_DISTRIBUTION_PERIOD_OPTIONS,
            'currency_options' => Currency::getAllCodes(),
            'successful_requests_count' => $distribution['total_successful'],
            'all_requests_count' => $distribution['total_all'],
            'successful_total_amount' => $this->formatDistributionTotalAmount($successfulTotalAmount, $currencyModel),
            'all_total_amount' => $this->formatDistributionTotalAmount($allTotalAmount, $currencyModel),
            'distribution' => $distribution,
        ];
    }

    /**
     * @param  Collection<int, object{amount_bucket: string, all_count: int|string, successful_count: int|string}>  $rows
     * @return array{
     *     buckets: list<array{
     *         key: string,
     *         label: string,
     *         successful_count: int,
     *         successful_percent: float,
     *         all_count: int,
     *         all_percent: float
     *     }>,
     *     total_successful: int,
     *     total_all: int
     * }
     */
    private function formatDualAmountDistribution(Collection $rows, string $currency): array
    {
        $successfulRows = $rows->map(
            fn (object $row): object => (object) [
                'amount_bucket' => $row->amount_bucket,
                'deals_count' => $row->successful_count,
            ],
        );
        $allRows = $rows->map(
            fn (object $row): object => (object) [
                'amount_bucket' => $row->amount_bucket,
                'deals_count' => $row->all_count,
            ],
        );

        $successfulDistribution = $this->volumeStatisticsService->formatModalDealAmountDistributionRows($successfulRows, $currency);
        $allDistribution = $this->volumeStatisticsService->formatModalDealAmountDistributionRows($allRows, $currency);

        $buckets = [];

        foreach ($successfulDistribution['buckets'] as $index => $successfulBucket) {
            $allBucket = $allDistribution['buckets'][$index] ?? [
                'key' => $successfulBucket['key'],
                'label' => $successfulBucket['label'],
                'count' => 0,
                'percent' => 0.0,
            ];

            $buckets[] = [
                'key' => $successfulBucket['key'],
                'label' => $successfulBucket['label'],
                'successful_count' => $successfulBucket['count'],
                'successful_percent' => $successfulBucket['percent'],
                'all_count' => $allBucket['count'],
                'all_percent' => $allBucket['percent'],
            ];
        }

        return [
            'buckets' => $buckets,
            'total_successful' => $successfulDistribution['total_deals'],
            'total_all' => $allDistribution['total_deals'],
        ];
    }

    private function formatDistributionTotalAmount(float $totalAmount, Currency $currency): string
    {
        if ($totalAmount <= 0) {
            return '0';
        }

        if (fmod($totalAmount, 1.0) === 0.0) {
            return number_format((int) round($totalAmount), 0, '', ' ');
        }

        $displayPrecision = $currency->getDisplayPrecision();

        return rtrim(
            rtrim(number_format($totalAmount, $displayPrecision, ',', ' '), '0'),
            ',',
        );
    }

    /**
     * Обновляет статистику за указанный период
     */
    public function updateStatistics(Carbon $fromDate, Carbon $toDate): void
    {
        // Получаем маппинг платежных шлюзов к валютам
        $paymentGateways = PaymentGateway::query()
            ->select('id', 'code', 'currency')
            ->get()
            ->pluck('currency', 'code')
            ->toArray();

        // Собираем статистику по дням
        $query = MerchantApiRequestLog::query()
            ->select([
                DB::raw('DATE(created_at) as date'),
                'is_successful',
                DB::raw('COALESCE(currency, payment_gateway) as currency_key'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as sum_amount'),
            ])
            ->tap(fn (Builder $query) => $this->applyOrderRequestTypeFilter($query))
            ->whereBetween('created_at', [$fromDate->toDateTimeString(), $toDate->toDateTimeString()])
            ->groupBy('date', 'is_successful', 'currency_key')
            ->orderBy('date'); // Явно указываем сортировку

        // Получаем все результаты сразу, так как группировка уже значительно уменьшает их количество
        $results = $query->get();

        // Сначала агрегируем данные по валюте, дате и успешности, чтобы избежать дублей из-за разных currency_key
        $grouped = [];

        foreach ($results as $row) {
            $currencyKey = $row->currency_key;
            $currency = $currencyKey;

            // Если currencyKey — это платёжный шлюз, а не валюта, получаем валюту из маппинга
            if (! Currency::isCurrency($currencyKey) && isset($paymentGateways[$currencyKey])) {
                $currency = $paymentGateways[$currencyKey];
            }

            // Ключ для группировки: дата + успешность + валюта
            $groupKey = $row->date.'|'.$row->is_successful.'|'.$currency;

            if (! isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'date' => $row->date,
                    'is_successful' => $row->is_successful,
                    'currency' => $currency,
                    'count' => 0,
                    'sum_amount' => 0,
                ];
            }

            $grouped[$groupKey]['count'] += $row->count;
            $grouped[$groupKey]['sum_amount'] += $row->sum_amount;
        }

        // Теперь обновляем или создаём записи по сгруппированным данным
        foreach ($grouped as $data) {
            MerchantApiStatistic::updateOrCreate(
                [
                    'date' => $data['date'],
                    'is_successful' => $data['is_successful'],
                    'currency' => $data['currency'],
                ],
                [
                    'count' => $data['count'],
                    'sum_amount' => $data['sum_amount'],
                ]
            );
        }
    }

    /**
     * Обновляет статистику за сегодня и вчера (для учета последних изменений)
     */
    public function updateTodayStatistics(): void
    {
        // Обновляем данные за вчера и сегодня для учета всех последних изменений
        $yesterday = now()->subDay()->startOfDay();
        $today = now()->endOfDay();
        $this->updateStatistics($yesterday, $today);
    }

    /**
     * Получает статистику за сегодня и за все время
     */
    public function getOrderCreateRequestsStats(Carbon $startDate, Carbon $endDate): array
    {
        $cacheKey = sprintf(
            'merchant_api_logs:order_create_processing:%s:%s',
            $startDate->toDateTimeString(),
            $endDate->toDateTimeString(),
        );

        return Cache::remember(
            $cacheKey,
            now()->addSeconds(self::ORDER_CREATE_PROCESSING_CACHE_TTL_SECONDS),
            fn (): array => $this->calculateOrderCreateRequestsStats($startDate, $endDate),
        );
    }

    /**
     * @return array{
     *     success_count: int,
     *     failed_count: int,
     *     total_count: int,
     *     processing_rate: float,
     *     processing_rate_formatted: string
     * }
     */
    private function calculateOrderCreateRequestsStats(Carbon $startDate, Carbon $endDate): array
    {
        $baseQuery = MerchantApiRequestLog::query()
            ->tap(fn (Builder $query) => $this->applyOrderRequestTypeFilter($query))
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalCount = (int) (clone $baseQuery)->count();
        $successCount = (int) (clone $baseQuery)->where('is_successful', true)->count();
        $failedCount = $totalCount - $successCount;
        $processingRate = $totalCount > 0
            ? round(($successCount / $totalCount) * 100, 2)
            : 0.0;

        return [
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'total_count' => $totalCount,
            'processing_rate' => $processingRate,
            'processing_rate_formatted' => $processingRate.'%',
        ];
    }

    public function getStatistics(): array
    {
        $today = now()->toDateString();

        return Cache::remember(
            "merchant_api_logs:dashboard_stats:{$today}",
            now()->addSeconds(self::DASHBOARD_STATS_CACHE_TTL_SECONDS),
            function () use ($today): array {
                // Успешные и неуспешные запросы за сегодня
                $todayStats = MerchantApiStatistic::query()
                    ->where('date', '=', $today)
                    ->get()
                    ->groupBy('is_successful');

                // Общая статистика за все время
                $totalStats = MerchantApiStatistic::select([
                    'is_successful',
                    'currency',
                    DB::raw('SUM(count) as total_count'),
                    DB::raw('SUM(sum_amount) as total_sum'),
                ])
                    ->groupBy('is_successful', 'currency')
                    ->get()
                    ->groupBy('is_successful');

                // Формируем результаты
                $successToday = $todayStats[true] ?? collect();
                $failedToday = $todayStats[false] ?? collect();
                $successTotal = $totalStats[true] ?? collect();
                $failedTotal = $totalStats[false] ?? collect();

                // Суммы по валютам
                $sumBySuccessCurrencyToday = $successToday->pluck('sum_amount', 'currency')->toArray();
                $sumByFailedCurrencyToday = $failedToday->pluck('sum_amount', 'currency')->toArray();
                $sumBySuccessCurrencyTotal = $successTotal->pluck('total_sum', 'currency')->toArray();
                $sumByFailedCurrencyTotal = $failedTotal->pluck('total_sum', 'currency')->toArray();

                // Общие количества
                $successTodayCount = $successToday->sum('count');
                $failedTodayCount = $failedToday->sum('count');
                $successTotalCount = $successTotal->sum('total_count');
                $failedTotalCount = $failedTotal->sum('total_count');

                return [
                    'successToday' => $successTodayCount,
                    'failedToday' => $failedTodayCount,
                    'successTotal' => $successTotalCount,
                    'failedTotal' => $failedTotalCount,
                    'sumBySuccessCurrencyToday' => $sumBySuccessCurrencyToday,
                    'sumByFailedCurrencyToday' => $sumByFailedCurrencyToday,
                    'sumBySuccessCurrencyTotal' => $sumBySuccessCurrencyTotal,
                    'sumByFailedCurrencyTotal' => $sumByFailedCurrencyTotal,
                ];
            },
        );
    }

    public function getHourlyRequestsChart(Carbon $date, ?User $merchantUser = null, array $filters = []): array
    {
        $normalizedFilters = $this->normalizeChartFilters($filters);
        $cacheKey = $this->buildRequestsChartCacheKey(
            'day',
            $date->toDateString(),
            [],
            $merchantUser,
            $normalizedFilters,
        );

        return Cache::remember(
            $cacheKey,
            now()->addSeconds(self::REQUESTS_CHART_CACHE_TTL_SECONDS),
            function () use ($date, $merchantUser, $normalizedFilters): array {
                $startDate = $date->copy()->startOfDay();
                $endDate = $date->copy()->endOfDay();

                $baseQuery = MerchantApiRequestLog::query()
                    ->tap(fn (Builder $query) => $this->applyOrderRequestTypeFilter($query))
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->when($merchantUser, function (Builder $query, User $user): void {
                        $query->whereRelation('merchant', 'user_id', $user->id);
                    });
                $this->applyChartFilters($baseQuery, $normalizedFilters);

                $totalByHour = (clone $baseQuery)
                    ->selectRaw('HOUR(created_at) as hour, COUNT(*) as aggregate')
                    ->groupBy('hour')
                    ->pluck('aggregate', 'hour');

                $successfulByHour = (clone $baseQuery)
                    ->where('is_successful', true)
                    ->selectRaw('HOUR(created_at) as hour, COUNT(*) as aggregate')
                    ->groupBy('hour')
                    ->pluck('aggregate', 'hour');

                $labels = [];
                $total = [];
                $successful = [];

                for ($hour = 0; $hour < 24; $hour++) {
                    $labels[] = str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00';
                    $total[] = (int) ($totalByHour[$hour] ?? 0);
                    $successful[] = (int) ($successfulByHour[$hour] ?? 0);
                }

                return [
                    'labels' => $labels,
                    'total' => $total,
                    'successful' => $successful,
                ];
            },
        );
    }

    public function getAverageHourlyRequestsChart(array $weekdays, ?User $merchantUser = null, array $filters = []): array
    {
        $weekdays = collect($weekdays)
            ->map(fn ($weekday) => (int) $weekday)
            ->filter(fn (int $weekday): bool => $weekday >= 1 && $weekday <= 7)
            ->unique()
            ->values()
            ->all();

        if (empty($weekdays)) {
            $weekdays = range(1, 7);
        }

        $normalizedFilters = $this->normalizeChartFilters($filters);
        $cacheKey = $this->buildRequestsChartCacheKey(
            'average',
            null,
            $weekdays,
            $merchantUser,
            $normalizedFilters,
        );

        return Cache::remember(
            $cacheKey,
            now()->addSeconds(self::REQUESTS_CHART_CACHE_TTL_SECONDS),
            function () use ($weekdays, $merchantUser, $normalizedFilters): array {
                $baseQuery = MerchantApiRequestLog::query()
                    ->tap(fn (Builder $query) => $this->applyOrderRequestTypeFilter($query))
                    ->when($merchantUser, function (Builder $query, User $user): void {
                        $query->whereRelation('merchant', 'user_id', $user->id);
                    });
                $this->applyChartFilters($baseQuery, $normalizedFilters);

                $firstLogDate = (clone $baseQuery)->min('created_at');
                $startDate = $firstLogDate ? Carbon::parse($firstLogDate)->startOfDay() : now()->startOfDay();
                $endDate = now()->endOfDay();
                $daysCount = $this->countWeekdaysInRange($startDate, $endDate, $weekdays);

                $weekdayList = implode(',', $weekdays);
                $averageBaseQuery = (clone $baseQuery)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->whereRaw("WEEKDAY(created_at) + 1 in ({$weekdayList})");

                $totalByHour = (clone $averageBaseQuery)
                    ->selectRaw('HOUR(created_at) as hour, COUNT(*) as aggregate')
                    ->groupBy('hour')
                    ->pluck('aggregate', 'hour');

                $successfulByHour = (clone $averageBaseQuery)
                    ->where('is_successful', true)
                    ->selectRaw('HOUR(created_at) as hour, COUNT(*) as aggregate')
                    ->groupBy('hour')
                    ->pluck('aggregate', 'hour');

                $labels = [];
                $total = [];
                $successful = [];

                for ($hour = 0; $hour < 24; $hour++) {
                    $labels[] = str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00';
                    $total[] = round(((int) ($totalByHour[$hour] ?? 0)) / $daysCount, 2);
                    $successful[] = round(((int) ($successfulByHour[$hour] ?? 0)) / $daysCount, 2);
                }

                return [
                    'labels' => $labels,
                    'total' => $total,
                    'successful' => $successful,
                    'daysCount' => $daysCount,
                ];
            },
        );
    }

    private function countWeekdaysInRange(Carbon $startDate, Carbon $endDate, array $weekdays): int
    {
        $selectedWeekdays = array_flip($weekdays);
        $daysCount = 0;
        $cursor = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->startOfDay();

        while ($cursor->lte($end)) {
            if (isset($selectedWeekdays[$cursor->dayOfWeekIso])) {
                $daysCount++;
            }

            $cursor->addDay();
        }

        return max($daysCount, 1);
    }

    private function applyChartFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['currency'])) {
            $query->whereRaw('LOWER(currency) = ?', [strtolower((string) $filters['currency'])]);
        }

        if (($filters['amount_from'] ?? null) !== null && $filters['amount_from'] !== '') {
            $query->whereRaw($this->normalizedAmountExpression().' >= ?', [(float) $filters['amount_from']]);
        }

        if (($filters['amount_to'] ?? null) !== null && $filters['amount_to'] !== '') {
            $query->whereRaw($this->normalizedAmountExpression().' <= ?', [(float) $filters['amount_to']]);
        }
    }

    private function applyOrderRequestTypeFilter(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query
                ->where('request_type', MerchantApiRequestLog::TYPE_ORDER)
                ->orWhereNull('request_type');
        });
    }

    private function normalizedAmountExpression(): string
    {
        return "CAST(REPLACE(REPLACE(amount, ' ', ''), ',', '.') AS DECIMAL(20, 8))";
    }

    private function normalizeChartFilters(array $filters): array
    {
        $currency = $filters['currency'] ?? null;
        $amountFrom = $filters['amount_from'] ?? null;
        $amountTo = $filters['amount_to'] ?? null;

        return [
            'currency' => is_string($currency) ? strtolower($currency) : null,
            'amount_from' => is_numeric($amountFrom) ? (float) $amountFrom : null,
            'amount_to' => is_numeric($amountTo) ? (float) $amountTo : null,
        ];
    }

    private function buildRequestsChartCacheKey(
        string $mode,
        ?string $date,
        array $weekdays,
        ?User $merchantUser,
        array $filters
    ): string {
        $normalizedWeekdays = collect($weekdays)
            ->map(fn ($weekday) => (int) $weekday)
            ->filter(fn (int $weekday): bool => $weekday >= 1 && $weekday <= 7)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $payload = [
            'mode' => $mode,
            'date' => $date,
            'weekdays' => $normalizedWeekdays,
            'merchant_user_id' => $merchantUser?->id,
            'filters' => $filters,
        ];

        return 'merchant_api_logs:requests_chart:'.md5((string) json_encode($payload));
    }
}
