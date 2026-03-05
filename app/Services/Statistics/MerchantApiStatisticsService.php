<?php

namespace App\Services\Statistics;

use App\Contracts\MerchantApiStatisticsServiceContract;
use App\Models\MerchantApiRequestLog;
use App\Models\MerchantApiStatistic;
use App\Models\PaymentGateway;
use App\Services\Money\Currency;
use Carbon\Carbon;
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
                DB::raw('SUM(amount) as sum_amount')
            ])
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
            if (!Currency::isCurrency($currencyKey) && isset($paymentGateways[$currencyKey])) {
                $currency = $paymentGateways[$currencyKey];
            }

            // Ключ для группировки: дата + успешность + валюта
            $groupKey = $row->date . '|' . $row->is_successful . '|' . $currency;

            if (!isset($grouped[$groupKey])) {
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
}
