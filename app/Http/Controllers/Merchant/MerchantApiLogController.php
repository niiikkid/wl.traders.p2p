<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\MerchantApiLog\AmountDistributionRequest;
use App\Http\Resources\CallbackLogResource;
use App\Http\Resources\MerchantApiLogResource;
use App\Models\MerchantApiRequestLog;
use App\Models\User;
use App\Services\Statistics\MerchantApiStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MerchantApiLogController extends Controller
{
    public function index(Request $request): Response
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

        // Статистика запросов вынесена на панель управления мерчанта (merchant.main.index).
        return Inertia::render('MerchantApiLogs/Index', [
            'logs' => MerchantApiLogResource::collection($logs),
            'filters' => $filters,
            'filtersVariants' => $filtersVariants,
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
