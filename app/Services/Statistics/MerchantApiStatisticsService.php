<?php

namespace App\Services\Statistics;

use App\Contracts\MerchantApiStatisticsServiceContract;
use App\Models\MerchantApiRequestLog;
use App\Models\MerchantApiStatistic;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Services\Money\Currency;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MerchantApiStatisticsService implements MerchantApiStatisticsServiceContract
{
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
    public function getStatistics(): array
    {
        $today = now()->toDateString();

        // Успешные и неуспешные запросы за сегодня
        $todayStats = MerchantApiStatistic::where('date', $today)
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
    }

    public function getHourlyRequestsChart(Carbon $date, ?User $merchantUser = null, array $filters = []): array
    {
        $startDate = $date->copy()->startOfDay();
        $endDate = $date->copy()->endOfDay();

        $baseQuery = MerchantApiRequestLog::query()
            ->tap(fn (Builder $query) => $this->applyOrderRequestTypeFilter($query))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($merchantUser, function (Builder $query, User $user): void {
                $query->whereRelation('merchant', 'user_id', $user->id);
            });
        $this->applyChartFilters($baseQuery, $filters);

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

        $baseQuery = MerchantApiRequestLog::query()
            ->tap(fn (Builder $query) => $this->applyOrderRequestTypeFilter($query))
            ->when($merchantUser, function (Builder $query, User $user): void {
                $query->whereRelation('merchant', 'user_id', $user->id);
            });
        $this->applyChartFilters($baseQuery, $filters);

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
}
