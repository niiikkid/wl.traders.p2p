<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AntiFraudLog;
use App\Services\EnabledCards\EnabledCardsStatsService;
use App\Services\Money\Currency;
use App\Services\Statistics\MerchantApiStatisticsService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class DashboardStatsController extends Controller
{
    /**
     * Anti-fraud decisions over the last 24 hours (global, no filters).
     */
    public function antiFraud(): JsonResponse
    {
        $now = now();
        $chartStart = $now->copy()->subHours(23)->startOfHour();
        $chartEnd = $now->copy()->startOfHour();

        $chartBaseQuery = AntiFraudLog::query()
            ->where('created_at', '>=', $chartStart)
            ->where('created_at', '<=', $chartEnd->copy()->endOfHour());

        $uniqueCounts = (clone $chartBaseQuery)
            ->whereNotNull('client_id')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour, COUNT(DISTINCT client_id) as unique_count")
            ->groupBy('hour')
            ->pluck('unique_count', 'hour');

        $hourlyClients = (clone $chartBaseQuery)
            ->whereNotNull('client_id')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour, client_id, COUNT(*) as total")
            ->groupBy('hour', 'client_id');

        $repeatedCounts = DB::query()
            ->fromSub($hourlyClients, 'hourly_clients')
            ->selectRaw('hour, COUNT(*) as repeated_count')
            ->where('total', '>=', 2)
            ->groupBy('hour')
            ->pluck('repeated_count', 'hour');

        $blockedCounts = (clone $chartBaseQuery)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour, SUM(CASE WHEN decision != 'allow' THEN 1 ELSE 0 END) as blocked_count")
            ->groupBy('hour')
            ->pluck('blocked_count', 'hour');

        $labels = [];
        $uniqueSeries = [];
        $repeatedSeries = [];
        $blockedSeries = [];

        foreach (CarbonPeriod::create($chartStart, '1 hour', $chartEnd) as $hour) {
            $hourKey = $hour->format('Y-m-d H:00:00');
            $labels[] = $hour->format('H:i');
            $uniqueSeries[] = (int) ($uniqueCounts[$hourKey] ?? 0);
            $repeatedSeries[] = (int) ($repeatedCounts[$hourKey] ?? 0);
            $blockedSeries[] = (int) ($blockedCounts[$hourKey] ?? 0);
        }

        return response()->json([
            'chart' => [
                'labels' => $labels,
                'series' => [
                    ['name' => 'Уникальные клиенты', 'data' => $uniqueSeries],
                    ['name' => 'Повторные клиенты', 'data' => $repeatedSeries],
                    ['name' => 'Блокировки', 'data' => $blockedSeries],
                ],
            ],
        ]);
    }

    /**
     * Merchant API request statistics (pay-in / order create requests).
     */
    public function merchantApi(Request $request, MerchantApiStatisticsService $statisticsService): JsonResponse
    {
        $statistics = $statisticsService->getStatistics();

        $chartDate = now()->startOfDay();
        $chartDateInput = $request->query('chart_date');

        if (is_string($chartDateInput)) {
            try {
                $parsedChartDate = Carbon::createFromFormat('Y-m-d', $chartDateInput);

                if ($parsedChartDate->format('Y-m-d') === $chartDateInput) {
                    $chartDate = $parsedChartDate->startOfDay();
                }
            } catch (Throwable) {
                $chartDate = now()->startOfDay();
            }
        }

        $chartMode = $request->query('chart_mode') === 'average' ? 'average' : 'day';
        $chartWeekdays = collect((array) $request->query('chart_weekdays', range(1, 7)))
            ->map(fn ($weekday) => (int) $weekday)
            ->filter(fn (int $weekday): bool => $weekday >= 1 && $weekday <= 7)
            ->unique()
            ->values()
            ->all();

        if (empty($chartWeekdays)) {
            $chartWeekdays = range(1, 7);
        }

        $chartCurrency = $request->query('chart_currency');
        $chartCurrency = is_string($chartCurrency) && in_array(strtolower($chartCurrency), Currency::getAllCodes(), true)
            ? strtolower($chartCurrency)
            : null;
        $chartAmountFrom = $request->query('chart_amount_from');
        $chartAmountTo = $request->query('chart_amount_to');
        $chartFilters = [
            'currency' => $chartCurrency,
            'amount_from' => is_numeric($chartAmountFrom) ? (float) $chartAmountFrom : null,
            'amount_to' => is_numeric($chartAmountTo) ? (float) $chartAmountTo : null,
        ];

        $requestsChart = $chartMode === 'average'
            ? $statisticsService->getAverageHourlyRequestsChart($chartWeekdays, null, $chartFilters)
            : $statisticsService->getHourlyRequestsChart($chartDate, null, $chartFilters);

        return response()->json([
            ...$statistics,
            'requestsChart' => $requestsChart,
            'requestsChartDate' => $chartDate->toDateString(),
            'requestsChartMode' => $chartMode,
            'requestsChartWeekdays' => $chartWeekdays,
            'requestsChartFilters' => $chartFilters,
            'chartCurrencyOptions' => Currency::getAllCodes(),
        ]);
    }

    /**
     * Enabled payment details statistics.
     */
    public function enabledCards(Request $request, EnabledCardsStatsService $service): JsonResponse
    {
        $detailType = $request->input('detail_type');
        $paymentGatewayId = $request->input('payment_gateway_id');
        $userId = $request->input('user_id');

        return response()->json([
            'statistics' => $service->build($detailType, $paymentGatewayId, $userId),
            'filters' => [
                'detail_type' => $detailType,
                'payment_gateway_id' => $paymentGatewayId,
                'user_id' => $userId,
            ],
        ]);
    }
}
