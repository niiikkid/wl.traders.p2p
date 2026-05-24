<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\EnabledCardMinAmountLevel;
use App\Models\Order;
use App\Models\PaymentDetail;
use App\Models\Wallet;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EnabledCardsController extends Controller
{
    public function storeLimitLevel(Request $request)
    {
        $validated = $request->validate([
            'currency' => ['required', 'string', Rule::in(Currency::getAllCodes())],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $currency = Currency::make($validated['currency']);
        $amount_units = (int) Money::fromPrecision((string) $validated['amount'], $currency->getCode())->toUnits();

        if ($amount_units <= 0) {
            return back()->withErrors([
                'limit_level' => 'Уровень лимита должен быть больше нуля.',
            ]);
        }

        EnabledCardMinAmountLevel::query()->firstOrCreate([
            'currency' => $currency->getCode(),
            'min_amount' => $amount_units,
        ]);

        return back();
    }

    public function destroyLimitLevel(Request $request)
    {
        $validated = $request->validate([
            'currency' => ['required', 'string', Rule::in(Currency::getAllCodes())],
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $currency = Currency::make($validated['currency']);

        EnabledCardMinAmountLevel::query()
            ->where('currency', $currency->getCode())
            ->where('min_amount', (int) $validated['amount'])
            ->delete();

        return back();
    }

    public function index(Request $request)
    {
        // Получаем параметры фильтрации
        $detailType = $request->input('detail_type');
        $paymentGatewayId = $request->input('payment_gateway_id');
        $userId = $request->input('user_id');

        // Получение общего количества включенных реквизитов
        $enabledPaymentDetailsCount = $this->trafficAvailablePaymentDetailsQuery($detailType, $paymentGatewayId, $userId)
            ->count();

        // Получение идентификаторов активных реквизитов для использования в запросах
        $activePaymentDetailIds = $this->trafficAvailablePaymentDetailsQuery($detailType, $paymentGatewayId, $userId)
            ->pluck('id');

        // Получение свободного лимита по каждой валюте
        $currencyLimits = $this->trafficAvailablePaymentDetailsQuery($detailType, $paymentGatewayId, $userId)
            ->select(
                'currency',
                DB::raw('COALESCE(SUM(CAST(daily_limit AS SIGNED) - CAST(current_daily_limit AS SIGNED)), 0) as total_free_limit')
            )
            ->groupBy('currency')
            ->get()
            ->map(function ($item) {
                // Создаем объект валюты для получения правильного имени и символа
                $currency = new Currency($item->currency);
                $freeLimit = Money::fromUnits((string) $item->total_free_limit, $currency->getCode())->toBeauty();

                return [
                    'code' => $currency->getCode(),
                    'name' => $currency->getName(),
                    'symbol' => $currency->getSymbol(),
                    'total_free_limit' => $freeLimit,
                ];
            });

        // Получение суммы активных заказов (в статусе PENDING) по каждой валюте
        $pendingOrderAmounts = Order::query()
            ->whereIn('payment_detail_id', $activePaymentDetailIds)
            ->where('status', OrderStatus::PENDING)
            ->select('currency', DB::raw('COALESCE(SUM(CAST(amount AS SIGNED)), 0) as total_amount'))
            ->groupBy('currency')
            ->get()
            ->mapWithKeys(function (Order $item) {
                return [$item->currency->getCode() => (int) $item->total_amount];
            });

        // Расчет потенциального лимита (свободный лимит - сумма активных заказов)
        $potentialLimits = $this->trafficAvailablePaymentDetailsQuery($detailType, $paymentGatewayId, $userId)
            ->select(
                'currency',
                DB::raw('COALESCE(SUM(CAST(daily_limit AS SIGNED) - CAST(current_daily_limit AS SIGNED)), 0) as total_free_limit')
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
                $formattedPotentialLimit = Money::fromUnits((string) $potentialLimit, $currency->getCode())->toBeauty();

                return [
                    'code' => $currency->getCode(),
                    'name' => $currency->getName(),
                    'symbol' => $currency->getSymbol(),
                    'total_potential_limit' => $formattedPotentialLimit,
                ];
            });

        // Общий баланс всех трейдеров с применением фильтра по пользователю, если указан
        $totalTradersBalanceQuery = Wallet::query();

        if ($userId) {
            $totalTradersBalanceQuery->where('user_id', $userId);
        } else {
            $totalTradersBalanceQuery->whereHas('user', function ($query) {
                // $query->role('Trader');
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
                // $query->role('Trader');
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
                    'symbol' => $currency->getSymbol(),
                ];
            })
            ->values()
            ->toArray();

        $minAmountLevels = EnabledCardMinAmountLevel::query()
            ->select(['currency', 'min_amount'])
            ->orderBy('min_amount')
            ->get()
            ->groupBy('currency')
            ->map(fn ($levels) => $levels->pluck('min_amount')->map(fn ($value) => (int) $value)->values()->all())
            ->toArray();

        // Получение статистики по группам минимальных лимитов
        $minAmountStats = [];

        foreach ($availableCurrencies as $currency) {
            $currencyCode = $currency['code'];
            $minAmountStats[$currencyCode] = [];

            $groups = collect($minAmountLevels[$currencyCode] ?? [])
                ->map(fn ($amount_units) => (int) $amount_units)
                ->filter(fn (int $amount_units) => $amount_units > 0)
                ->unique()
                ->sort()
                ->values()
                ->map(function (int $amount_units) use ($currencyCode) {
                    $amount = Money::fromUnits((string) $amount_units, $currencyCode)->toBeauty();

                    return [
                        'title' => "От {$amount}",
                        'min_amount' => $amount_units,
                    ];
                })
                ->prepend([
                    'title' => 'Не указан',
                    'min_amount' => null,
                ])
                ->values()
                ->all();

            foreach ($groups as $group) {
                // Базовый запрос для активных реквизитов выбранной валюты
                $query = $this->trafficAvailablePaymentDetailsQuery($detailType, $paymentGatewayId, $userId)
                    ->where('currency', $currencyCode);

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
                $count = (clone $query)->count();

                // Свободный лимит для реквизитов в группе
                $freeLimit = (int) ((clone $query)->toBase()->selectRaw(
                    'COALESCE(SUM(CAST(daily_limit AS SIGNED) - CAST(current_daily_limit AS SIGNED)), 0) as free_limit'
                )->value('free_limit') ?? 0);

                // ID реквизитов в группе для расчета потенциального лимита
                $detailIds = (clone $query)->pluck('id')->toArray();

                // Сумма ожидающих заказов для реквизитов группы
                $pendingAmount = 0;

                if (! empty($detailIds)) {
                    $pendingAmount = (int) (Order::query()
                        ->whereIn('payment_detail_id', $detailIds)
                        ->where('status', OrderStatus::PENDING)
                        ->where('currency', $currencyCode)
                        ->toBase()
                        ->selectRaw('COALESCE(SUM(CAST(amount AS SIGNED)), 0) as pending_amount')
                        ->value('pending_amount') ?? 0);
                }

                // Расчет потенциального лимита
                $potentialLimit = $freeLimit + $pendingAmount;

                $minAmountStats[$currencyCode][] = [
                    'title' => $group['title'],
                    'min_amount' => $group['min_amount'],
                    'count' => $count,
                    'free_limit' => Money::fromUnits((string) $freeLimit, $currencyCode)->toBeauty(),
                    'potential_limit' => Money::fromUnits((string) $potentialLimit, $currencyCode)->toBeauty(),
                ];
            }
        }

        // Форматируем баланс для отображения
        $formattedTotalBalance = Money::fromUnits((string) $totalTradersBalance, Currency::USDT()->getCode())->toBeauty();
        $formattedOnlineBalance = Money::fromUnits((string) $onlineTradersBalance, Currency::USDT()->getCode())->toBeauty();

        return Inertia::render('EnabledCards/Index', [
            'statistics' => [
                'totalPaymentDetails' => $enabledPaymentDetailsCount,
                'currencyLimits' => $currencyLimits,
                'potentialLimits' => $potentialLimits,
                'availableCurrencies' => $availableCurrencies,
                'minAmountLevels' => $minAmountLevels,
                'minAmountStats' => $minAmountStats,
                'tradersBalance' => [
                    'total' => $formattedTotalBalance,
                    'online' => $formattedOnlineBalance,
                    'currency' => Currency::USDT()->getCode(),
                    'symbol' => Currency::USDT()->getSymbol(),
                ],
            ],
            'filters' => [
                'detail_type' => $detailType,
                'payment_gateway_id' => $paymentGatewayId,
                'user_id' => $userId,
            ],
        ]);
    }

    private function trafficAvailablePaymentDetailsQuery(
        ?string $detailType,
        mixed $paymentGatewayId,
        mixed $userId,
    ): Builder {
        $query = PaymentDetail::query()
            ->whereNull('archived_at')
            ->where('is_active', true)
            ->whereRelation('user', 'is_online', true)
            ->availableBySchedule();

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

        return $query;
    }
}
