<?php

namespace App\Services\EnabledCards;

use App\Enums\OrderStatus;
use App\Models\EnabledCardMinAmountLevel;
use App\Models\Order;
use App\Models\PaymentDetail;
use App\Models\Wallet;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EnabledCardsStatsService
{
    /**
     * Build the enabled payment details statistics for the admin dashboard widget.
     *
     * @return array{
     *     totalPaymentDetails: int,
     *     currencyLimits: array<int, array{code: string, name: string, symbol: string, total_free_limit: string}>,
     *     potentialLimits: array<int, array{code: string, name: string, symbol: string, total_potential_limit: string}>,
     *     availableCurrencies: array<int, array{code: string, name: string, symbol: string}>,
     *     minAmountLevels: array<string, array<int, int>>,
     *     minAmountStats: array<string, array<int, array<string, mixed>>>,
     *     tradersBalance: array{total: string, online: string, currency: string, symbol: string}
     * }
     */
    public function build(?string $detailType, mixed $paymentGatewayId, mixed $userId): array
    {
        $enabledPaymentDetailsCount = $this->trafficAvailablePaymentDetailsQuery($detailType, $paymentGatewayId, $userId)
            ->count();

        $activePaymentDetailIds = $this->trafficAvailablePaymentDetailsQuery($detailType, $paymentGatewayId, $userId)
            ->pluck('id');

        $currencyLimits = $this->trafficAvailablePaymentDetailsQuery($detailType, $paymentGatewayId, $userId)
            ->select(
                'currency',
                DB::raw('COALESCE(SUM(CAST(daily_limit AS SIGNED) - CAST(current_daily_limit AS SIGNED)), 0) as total_free_limit')
            )
            ->groupBy('currency')
            ->get()
            ->map(function ($item) {
                $currency = new Currency($item->currency);
                $freeLimit = Money::fromUnits((string) $item->total_free_limit, $currency->getCode())->toBeauty();

                return [
                    'code' => $currency->getCode(),
                    'name' => $currency->getName(),
                    'symbol' => $currency->getSymbol(),
                    'total_free_limit' => $freeLimit,
                ];
            });

        $pendingOrderAmounts = Order::query()
            ->whereIn('payment_detail_id', $activePaymentDetailIds)
            ->where('status', OrderStatus::PENDING)
            ->select('currency', DB::raw('COALESCE(SUM(CAST(amount AS SIGNED)), 0) as total_amount'))
            ->groupBy('currency')
            ->get()
            ->mapWithKeys(function (Order $item) {
                return [$item->currency->getCode() => (int) $item->total_amount];
            });

        $potentialLimits = $this->trafficAvailablePaymentDetailsQuery($detailType, $paymentGatewayId, $userId)
            ->select(
                'currency',
                DB::raw('COALESCE(SUM(CAST(daily_limit AS SIGNED) - CAST(current_daily_limit AS SIGNED)), 0) as total_free_limit')
            )
            ->groupBy('currency')
            ->get()
            ->map(function ($item) use ($pendingOrderAmounts) {
                $currency = new Currency($item->currency);
                $pendingAmount = $pendingOrderAmounts[$item->currency->getCode()] ?? 0;

                $potentialLimit = $item->total_free_limit + $pendingAmount;
                $formattedPotentialLimit = Money::fromUnits((string) $potentialLimit, $currency->getCode())->toBeauty();

                return [
                    'code' => $currency->getCode(),
                    'name' => $currency->getName(),
                    'symbol' => $currency->getSymbol(),
                    'total_potential_limit' => $formattedPotentialLimit,
                ];
            });

        $totalTradersBalanceQuery = Wallet::query();

        if ($userId) {
            $totalTradersBalanceQuery->where('user_id', $userId);
        }

        $totalTradersBalance = $totalTradersBalanceQuery->sum('trust_balance');

        $onlineTradersBalanceQuery = Wallet::query();

        if ($userId) {
            $onlineTradersBalanceQuery->where('user_id', $userId)
                ->whereRelation('user', 'is_online', true);
        } else {
            $onlineTradersBalanceQuery->whereHas('user', function ($query) {
                $query->where('is_online', true);
            });
        }

        $onlineTradersBalance = $onlineTradersBalanceQuery->sum('trust_balance');

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

        $minAmountStats = [];

        foreach ($availableCurrencies as $currency) {
            $currencyCode = $currency['code'];
            $minAmountStats[$currencyCode] = [];

            $groups = collect($minAmountLevels[$currencyCode] ?? [])
                ->map(fn ($amountUnits) => (int) $amountUnits)
                ->filter(fn (int $amountUnits) => $amountUnits > 0)
                ->unique()
                ->sort()
                ->values()
                ->map(function (int $amountUnits) use ($currencyCode) {
                    $amount = Money::fromUnits((string) $amountUnits, $currencyCode)->toBeauty();

                    return [
                        'title' => "От {$amount}",
                        'min_amount' => $amountUnits,
                    ];
                })
                ->prepend([
                    'title' => 'Не указан',
                    'min_amount' => null,
                ])
                ->values()
                ->all();

            foreach ($groups as $group) {
                $query = $this->trafficAvailablePaymentDetailsQuery($detailType, $paymentGatewayId, $userId)
                    ->where('currency', $currencyCode);

                if ($group['min_amount'] === null) {
                    $query->whereNull('min_order_amount');
                } else {
                    $query->whereNotNull('min_order_amount')
                        ->where('min_order_amount', '<=', $group['min_amount']);
                }

                $count = (clone $query)->count();

                $freeLimit = (int) ((clone $query)->toBase()->selectRaw(
                    'COALESCE(SUM(CAST(daily_limit AS SIGNED) - CAST(current_daily_limit AS SIGNED)), 0) as free_limit'
                )->value('free_limit') ?? 0);

                $detailIds = (clone $query)->pluck('id')->toArray();

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

        $formattedTotalBalance = Money::fromUnits((string) $totalTradersBalance, Currency::USDT()->getCode())->toBeauty();
        $formattedOnlineBalance = Money::fromUnits((string) $onlineTradersBalance, Currency::USDT()->getCode())->toBeauty();

        return [
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
        ];
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
            ->whereRelation('user', 'stop_traffic', false)
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
