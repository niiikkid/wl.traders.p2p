<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MerchantApiLogResource;
use App\Models\MerchantApiRequestLog;
use App\Services\Statistics\MerchantApiStatisticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MerchantApiLogController extends Controller
{
    public function index(MerchantApiStatisticsService $statisticsService)
    {
        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $logs = queries()->merchantApiLog()->paginateForAdmin($filters);
        
        // Получаем статистику из сервиса
        $statistics = $statisticsService->getStatistics();
        
        // Распаковываем переменные
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
        ]);
    }
    
    /**
     * Удаляет логи API запросов в указанном диапазоне дат
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteByDateRange(Request $request)
    {
        // Проверка прав доступа - только администратор
        if (!auth()->user()->hasRole('Super Admin')) {
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
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->delete();
        
        return back()->with('message', "Удалено {$deletedCount} логов за период с {$request->start_date} по {$request->end_date}");
    }
}
