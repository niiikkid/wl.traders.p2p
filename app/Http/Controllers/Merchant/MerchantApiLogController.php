<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\MerchantApiLog\AmountDistributionRequest;
use App\Http\Resources\CallbackLogResource;
use App\Http\Resources\MerchantApiLogResource;
use App\Models\MerchantApiRequestLog;
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\Statistics\MerchantApiStatisticsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MerchantApiLogController extends Controller
{
    public function index(Request $request, MerchantApiStatisticsService $statisticsService): Response
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $filters = $this->getTableFilters();
        $filtersVariants = [
            'apiLogStatuses' => [
                [
                    'name' => 'Успешные',
                    'value' => '1',
                ],
                [
                    'name' => 'Неуспешные',
                    'value' => '0',
                ],
            ],
        ];

        $activeApiLogTab = match ($request->query('tab')) {
            'payouts' => 'payouts',
            'callbacks' => 'callbacks',
            default => 'orders',
        };

        if ($activeApiLogTab === 'callbacks') {
            $logs = queries()->callbackLog()->paginateForMerchant($user, $filters);

            return Inertia::render('MerchantApiLogs/Index', [
                'logs' => CallbackLogResource::collection($logs),
                'filters' => $filters,
                'filtersVariants' => [],
                'activeApiLogTab' => $activeApiLogTab,
            ]);
        }

        $requestType = $activeApiLogTab === 'payouts'
            ? MerchantApiRequestLog::TYPE_PAYOUT
            : MerchantApiRequestLog::TYPE_ORDER;

        $logs = queries()->merchantApiLog()->paginateForMerchant($user, $filters, $requestType);

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

        extract($statistics);

        return Inertia::render('MerchantApiLogs/Index', [
            'logs' => MerchantApiLogResource::collection($logs),
            'filters' => $filters,
            'filtersVariants' => $filtersVariants,
            'failedTotal' => $failedTotal,
            'failedToday' => $failedToday,
            'successTotal' => $successTotal,
            'successToday' => $successToday,
            'sumBySuccessCurrencyToday' => $sumBySuccessCurrencyToday,
            'sumByFailedCurrencyToday' => $sumByFailedCurrencyToday,
            'sumBySuccessCurrencyTotal' => $sumBySuccessCurrencyTotal,
            'sumByFailedCurrencyTotal' => $sumByFailedCurrencyTotal,
            'requestsChart' => $requestsChart,
            'requestsChartDate' => $chartDate->toDateString(),
            'requestsChartMode' => $chartMode,
            'requestsChartWeekdays' => $chartWeekdays,
            'requestsChartFilters' => $chartFilters,
            'chartCurrencyOptions' => Currency::getAllCodes(),
            'can_manage_merchant_api_log_deletion' => false,
            'activeApiLogTab' => $activeApiLogTab,
        ]);
    }

    public function amountDistribution(
        AmountDistributionRequest $request,
        MerchantApiStatisticsService $statisticsService,
    ): JsonResponse {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        return response()->json(
            $statisticsService->getAmountDistribution(
                $request->currency(),
                $request->period(),
                $user,
            ),
        );
    }
}
