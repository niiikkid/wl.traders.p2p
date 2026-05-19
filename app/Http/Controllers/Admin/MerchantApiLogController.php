<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MerchantApiLogResource;
use App\Models\MerchantApiRequestLog;
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\Statistics\MerchantApiStatisticsService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Throwable;

class MerchantApiLogController extends Controller
{
    public function index(Request $request, MerchantApiStatisticsService $statisticsService)
    {
        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }
        if ($user->hasRole('Merchant') && ! $user->hasRole('Super Admin')) {
            abort(404);
        }

        $activeApiLogTab = $request->query('tab') === 'payouts' ? 'payouts' : 'orders';
        $requestType = $activeApiLogTab === 'payouts'
            ? MerchantApiRequestLog::TYPE_PAYOUT
            : MerchantApiRequestLog::TYPE_ORDER;

        $logs = queries()->merchantApiLog()->paginateForAdmin($filters, $requestType);

        // Получаем статистику из сервиса
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
        $merchantUser = null;
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
            ? $statisticsService->getAverageHourlyRequestsChart($chartWeekdays, $merchantUser, $chartFilters)
            : $statisticsService->getHourlyRequestsChart($chartDate, $merchantUser, $chartFilters);

        // Распаковываем переменные
        extract($statistics);

        $can_manage_merchant_api_log_deletion = $user->hasRole('Super Admin')
            && ! $request->routeIs('analyst.*');

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
            'can_manage_merchant_api_log_deletion' => $can_manage_merchant_api_log_deletion,
            'activeApiLogTab' => $activeApiLogTab,
        ]);
    }

    /**
     * Удаляет логи API запросов в указанном диапазоне дат
     *
     * @return RedirectResponse
     */
    public function deleteByDateRange(Request $request)
    {
        // Проверка прав доступа - только администратор
        $user = Auth::user();
        if (! $user instanceof User || ! $user->hasRole('Super Admin')) {
            abort(403);
        }

        $request->validate([
            'start_date' => 'required|date_format:d/m/Y',
            'end_date' => 'required|date_format:d/m/Y',
        ]);

        // Преобразование дат
        $startDate = Carbon::createFromFormat('d/m/Y', $request->start_date)->startOfDay();
        $endDate = Carbon::createFromFormat('d/m/Y', $request->end_date)->endOfDay();

        // Проверка корректности диапазона дат
        if ($endDate->lessThan($startDate)) {
            return back()->withErrors(['date_range' => 'Дата окончания не может быть раньше даты начала']);
        }

        // Удаляем логи в указанном диапазоне
        $deletedCount = MerchantApiRequestLog::query()
            ->where(function ($query): void {
                $query
                    ->where('request_type', MerchantApiRequestLog::TYPE_ORDER)
                    ->orWhereNull('request_type');
            })
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->delete();

        return back()->with('message', "Удалено {$deletedCount} логов за период с {$request->start_date} по {$request->end_date}");
    }
}
