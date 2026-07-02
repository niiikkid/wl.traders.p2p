<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\Statistics\MerchantApiStatisticsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class DashboardStatsController extends Controller
{
    /**
     * Merchant API request statistics (pay-in / order create requests),
     * scoped to the authenticated merchant user's shops. Mirrors the admin
     * dashboard endpoint but constrained to the current user.
     */
    public function merchantApi(Request $request, MerchantApiStatisticsService $statisticsService): JsonResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $statistics = $statisticsService->getStatisticsForMerchant($user);

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
            ? $statisticsService->getAverageHourlyRequestsChart($chartWeekdays, $user, $chartFilters)
            : $statisticsService->getHourlyRequestsChart($chartDate, $user, $chartFilters);

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
}
