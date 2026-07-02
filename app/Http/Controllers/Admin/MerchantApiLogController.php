<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MerchantApiLog\AmountDistributionRequest;
use App\Http\Resources\CallbackLogResource;
use App\Http\Resources\MerchantApiLogResource;
use App\Models\MerchantApiRequestLog;
use App\Models\User;
use App\Services\Statistics\MerchantApiStatisticsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MerchantApiLogController extends Controller
{
    public function index(Request $request)
    {
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
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }
        if ($user->hasRole('Merchant') && ! $user->hasRole('Super Admin')) {
            abort(404);
        }

        $activeApiLogTab = match ($request->query('tab')) {
            'payouts' => 'payouts',
            'callbacks' => 'callbacks',
            default => 'orders',
        };

        if ($activeApiLogTab === 'callbacks') {
            $logs = queries()->callbackLog()->paginateForAdmin($filters);

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

        $logs = queries()->merchantApiLog()->paginateForAdmin($filters, $requestType);

        // Статистика запросов вынесена на панель управления администратора (admin.main.index).
        $can_manage_merchant_api_log_deletion = $user->hasRole('Super Admin');

        return Inertia::render('MerchantApiLogs/Index', [
            'logs' => MerchantApiLogResource::collection($logs),
            'filters' => $filters,
            'filtersVariants' => $filtersVariants,
            'can_manage_merchant_api_log_deletion' => $can_manage_merchant_api_log_deletion,
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
        if ($user->hasRole('Merchant') && ! $user->hasRole('Super Admin')) {
            abort(404);
        }

        return response()->json(
            $statisticsService->getAmountDistribution(
                $request->currency(),
                $request->period(),
            ),
        );
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
