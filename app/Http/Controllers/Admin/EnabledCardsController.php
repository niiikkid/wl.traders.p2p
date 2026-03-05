<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentDetail;
use App\Services\Money\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Wallet;

class EnabledCardsController extends Controller
{
    public function index(Request $request)
    {
        // Получаем параметры фильтрации
        $detailType = $request->input('detail_type');
        $paymentGatewayId = $request->input('payment_gateway_id');
        $userId = $request->input('user_id');

        // Получение общего количества включенных реквизитов
        $query = PaymentDetail::query()
            ->whereNull('archived_at')
            ->where('is_active', true)
            ->whereRelation('user', 'is_online', true);
            
        // Применяем фильтры
        if ($detailType) {
            $query->where('detail_type', $detailType);
        }
        
        if ($paymentGatewayId) {
            $query->whereHas('paymentGateways', function ($q) use ($paymentGatewayId) {
                $q->where('payment_gateways.id', $paymentGatewayId);
            });
        }
        
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        $enabledPaymentDetailsCount = $query->count();

        // Получение идентификаторов активных реквизитов для использования в запросах
        $activePaymentDetailIdsQuery = PaymentDetail::query()
            ->whereNull('archived_at')
            ->where('is_active', true)
            ->whereRelation('user', 'is_online', true);
            
        // Применяем фильтры
        if ($detailType) {
            $activePaymentDetailIdsQuery->where('detail_type', $detailType);
        }
        
        if ($paymentGatewayId) {
            $activePaymentDetailIdsQuery->whereHas('paymentGateways', function ($q) use ($paymentGatewayId) {
                $q->where('payment_gateways.id', $paymentGatewayId);
            });
        }
        
        if ($userId) {
            $activePaymentDetailIdsQuery->where('user_id', $userId);
        }
        
        $activePaymentDetailIds = $activePaymentDetailIdsQuery->pluck('id');

        // Получение свободного лимита по каждой валюте
        $currencyLimitsQuery = PaymentDetail::query()
            ->whereNull('archived_at')
            ->where('is_active', true)
            ->whereRelation('user', 'is_online', true);
            
        // Применяем фильтры
        if ($detailType) {
            $currencyLimitsQuery->where('detail_type', $detailType);
        }
        
        if ($paymentGatewayId) {
            $currencyLimitsQuery->whereHas('paymentGateways', function ($q) use ($paymentGatewayId) {
                $q->where('payment_gateways.id', $paymentGatewayId);
            });
        }
        
        if ($userId) {
            $currencyLimitsQuery->where('user_id', $userId);
        }
        
        $currencyLimits = $currencyLimitsQuery
            ->select(
                'currency',
                DB::raw('SUM(CAST(daily_limit AS DECIMAL) - CAST(current_daily_limit AS DECIMAL)) as total_free_limit')
            )
            ->groupBy('currency')
            ->get()
            ->map(function ($item) {
                // Создаем объект валюты для получения правильного имени и символа
                $currency = new Currency($item->currency);

                return [
                    'code' => $currency->getCode(),
                    'name' => $currency->getName(),
                    'symbol' => $currency->getSymbol(),
                    'total_free_limit' => number_format($item->total_free_limit / 100, 2, '.', ' ')
                ];
            });

        // Получение суммы активных заказов (в статусе PENDING) по каждой валюте
        $pendingOrderAmounts = Order::query()
            ->whereIn('payment_detail_id', $activePaymentDetailIds)
            ->where('status', OrderStatus::PENDING)
            ->select('currency', DB::raw('SUM(CAST(amount AS DECIMAL)) as total_amount'))
            ->groupBy('currency')
            ->get()
            ->mapWithKeys(function (Order $item) {
                return [$item->currency->getCode() => $item->total_amount];
            });

        // Расчет потенциального лимита (свободный лимит - сумма активных заказов)
        $potentialLimitsQuery = PaymentDetail::query()
            ->whereNull('archived_at')
            ->where('is_active', true)
            ->whereRelation('user', 'is_online', true);
            
        // Применяем фильтры
        if ($detailType) {
            $potentialLimitsQuery->where('detail_type', $detailType);
        }
        
        if ($paymentGatewayId) {
            $potentialLimitsQuery->whereHas('paymentGateways', function ($q) use ($paymentGatewayId) {
                $q->where('payment_gateways.id', $paymentGatewayId);
            });
        }
        
        if ($userId) {
            $potentialLimitsQuery->where('user_id', $userId);
        }
        
        $potentialLimits = $potentialLimitsQuery
            ->select(
                'currency',
                DB::raw('SUM(CAST(daily_limit AS DECIMAL) - CAST(current_daily_limit AS DECIMAL)) as total_free_limit')
            )
            ->groupBy('currency')
            ->get()
            ->map(function ($item) use ($pendingOrderAmounts) {
                $currency = new Currency($item->currency);
                $pendingAmount = isset($pendingOrderAmounts[$item->currency->getCode()])
                    ? $pendingOrderAmounts[$item->currency->getCode()]
                    : 0;

                // Вычисляем потенциальный лимит
                $potentialLimit = $item->total_free_limit + $pendingAmount;

                return [
                    'code' => $currency->getCode(),
                    'name' => $currency->getName(),
                    'symbol' => $currency->getSymbol(),
                    'total_potential_limit' => number_format($potentialLimit / 100, 2, '.', ' ')
                ];
            });

        // Общий баланс всех трейдеров с применением фильтра по пользователю, если указан
        $totalTradersBalanceQuery = Wallet::query();
        
        if ($userId) {
            $totalTradersBalanceQuery->where('user_id', $userId);
        } else {
            $totalTradersBalanceQuery->whereHas('user', function ($query) {
                //$query->role('Trader');
            });
        }
        
        $totalTradersBalance = $totalTradersBalanceQuery->sum('trust_balance');

        // Общий баланс всех онлайн-трейдеров с применением фильтра по пользователю, если указан
        $onlineTradersBalanceQuery = Wallet::query();
        
        if ($userId) {
            $onlineTradersBalanceQuery->where('user_id', $userId)
                ->whereRelation('user', 'is_online', true);
        } else {
            $onlineTradersBalanceQuery->whereHas('user', function ($query) {
                //$query->role('Trader');
                $query->where('is_online', true);
            });
        }
        
        $onlineTradersBalance = $onlineTradersBalanceQuery->sum('trust_balance');

        // Список всех валют для селекта
        $availableCurrencies = Currency::getAll()
            ->map(function ($currency) {
                return [
                    'code' => $currency->getCode(),
                    'name' => $currency->getName(),
                    'symbol' => $currency->getSymbol()
                ];
            })
            ->values()
            ->toArray();

        // Определение лимитных групп для таблицы
        $minAmountGroups = [
            'no_limit' => ['title' => 'Не указан', 'min_amount' => null],
            '1k' => ['title' => 'От 1,000', 'min_amount' => 100000],
            '2k' => ['title' => 'От 2,000', 'min_amount' => 200000],
            '3k' => ['title' => 'От 3,000', 'min_amount' => 300000],
            '4k' => ['title' => 'От 4,000', 'min_amount' => 400000],
            '5k' => ['title' => 'От 5,000', 'min_amount' => 500000],
            '10k' => ['title' => 'От 10,000', 'min_amount' => 1000000],
            '20k' => ['title' => 'От 20,000', 'min_amount' => 2000000],
            '50k' => ['title' => 'От 50,000', 'min_amount' => 5000000],
        ];

        // Получение статистики по группам минимальных лимитов
        $minAmountStats = [];

        foreach ($availableCurrencies as $currency) {
            $currencyCode = $currency['code'];
            $minAmountStats[$currencyCode] = [];

            foreach ($minAmountGroups as $groupKey => $group) {
                // Базовый запрос для активных реквизитов выбранной валюты
                $query = PaymentDetail::query()
                    ->whereNull('archived_at')
                    ->where('is_active', true)
                    ->whereRelation('user', 'is_online', true)
                    ->where('currency', $currencyCode);

                // Применяем фильтры
                if ($detailType) {
                    $query->where('detail_type', $detailType);
                }
                
                if ($paymentGatewayId) {
                    $query->whereHas('paymentGateways', function ($q) use ($paymentGatewayId) {
                        $q->where('payment_gateways.id', $paymentGatewayId);
                    });
                }
                
                if ($userId) {
                    $query->where('user_id', $userId);
                }

                if ($group['min_amount'] === null) {
                    // Группа "Минимальный лимит не указан"
                    $query->whereNull('min_order_amount');
                } else {
                    // Другие группы с указанным минимальным лимитом
                    $query->whereNotNull('min_order_amount')
                        ->where('min_order_amount', '<=', $group['min_amount']);

                    /*// Дополнительное условие для верхней границы группы (кроме последней группы)
                    $nextGroup = next($minAmountGroups);

                    if ($nextGroup && isset($nextGroup['min_amount'])) {
                        $query->where('min_order_amount', '<', $nextGroup['min_amount']);
                    }
                    reset($minAmountGroups); // Сбрасываем указатель массива*/
                }

                // Подсчет количества реквизитов в группе
                $count = $query->count();

                // Свободный лимит для реквизитов в группе
                $freeLimit = $query->sum(DB::raw('CAST(daily_limit AS DECIMAL) - CAST(current_daily_limit AS DECIMAL)'));

                // ID реквизитов в группе для расчета потенциального лимита
                $detailIds = $query->pluck('id')->toArray();

                // Сумма ожидающих заказов для реквизитов группы
                $pendingAmount = Order::query()
                    ->whereIn('payment_detail_id', $detailIds)
                    ->where('status', OrderStatus::PENDING)
                    ->where('currency', $currencyCode)
                    ->sum('amount');

                // Расчет потенциального лимита
                $potentialLimit = $freeLimit + $pendingAmount;

                $minAmountStats[$currencyCode][$groupKey] = [
                    'title' => $group['title'],
                    'count' => $count,
                    'free_limit' => number_format($freeLimit / 100, 2, '.', ' '),
                    'potential_limit' => number_format($potentialLimit / 100, 2, '.', ' ')
                ];
            }
        }

        // Форматируем баланс для отображения
        $formattedTotalBalance = number_format($totalTradersBalance / 100, 2, '.', ' ');
        $formattedOnlineBalance = number_format($onlineTradersBalance / 100, 2, '.', ' ');

        return Inertia::render('EnabledCards/Index', [
            'statistics' => [
                'totalPaymentDetails' => $enabledPaymentDetailsCount,
                'currencyLimits' => $currencyLimits,
                'potentialLimits' => $potentialLimits,
                'availableCurrencies' => $availableCurrencies,
                'minAmountStats' => $minAmountStats,
                'tradersBalance' => [
                    'total' => $formattedTotalBalance,
                    'online' => $formattedOnlineBalance,
                    'currency' => Currency::USDT()->getCode(),
                    'symbol' => Currency::USDT()->getSymbol()
                ]
            ],
            'filters' => [
                'detail_type' => $detailType,
                'payment_gateway_id' => $paymentGatewayId,
                'user_id' => $userId
            ]
        ]);
    }
}
