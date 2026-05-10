<?php

namespace App\Services\EnabledCards;

use App\Enums\OrderStatus;
use App\Models\EnabledCardMinAmountLevel;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\PaymentDetail;
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MinAmountStatsService
{
    /**
     * Build enabled payment detail statistics by minimum limits for merchant's GEO currencies.
     *
     * @return array{
     *     availableCurrencies: array<int, array{code: string, name: string, symbol: string}>,
     *     minAmountStats: array<string, array<int, array{title: string, min_amount: int|null, count: int, free_limit: string, potential_limit: string}>>
     * }
     */
    public function buildForMerchantUser(User $user): array
    {
        $merchantCurrencyCodes = $this->getMerchantGeoCurrencyCodes($user);
        $levelCurrencyCodes = $this->getConfiguredLevelCurrencyCodes();
        $currencyCodes = $merchantCurrencyCodes
            ->intersect($levelCurrencyCodes)
            ->values();

        return $this->buildForCurrencies($currencyCodes);
    }

    /**
     * @param  Collection<int, string>  $currencyCodes
     * @return array{
     *     availableCurrencies: array<int, array{code: string, name: string, symbol: string}>,
     *     minAmountStats: array<string, array<int, array{title: string, min_amount: int|null, count: int, free_limit: string, potential_limit: string}>>
     * }
     */
    private function buildForCurrencies(Collection $currencyCodes): array
    {
        $currencyCodes = $currencyCodes
            ->map(fn (string $currencyCode) => strtolower($currencyCode))
            ->filter(fn (string $currencyCode) => in_array($currencyCode, Currency::getAllCodes(), true))
            ->unique()
            ->values();

        if ($currencyCodes->isEmpty()) {
            return [
                'availableCurrencies' => [],
                'minAmountStats' => [],
            ];
        }

        $minAmountLevels = EnabledCardMinAmountLevel::query()
            ->select(['currency', 'min_amount'])
            ->whereIn('currency', $currencyCodes)
            ->orderBy('min_amount')
            ->get()
            ->groupBy('currency')
            ->map(fn (Collection $levels) => $levels
                ->pluck('min_amount')
                ->map(fn (int $value) => $value)
                ->filter(fn (int $value) => $value > 0)
                ->unique()
                ->values());

        $availableCurrencies = Currency::getAll()
            ->filter(fn (Currency $currency) => $currencyCodes->contains($currency->getCode()))
            ->map(fn (Currency $currency) => [
                'code' => $currency->getCode(),
                'name' => $currency->getName(),
                'symbol' => $currency->getSymbol(),
            ])
            ->values()
            ->toArray();

        $minAmountStats = [];

        foreach ($availableCurrencies as $currency) {
            $currencyCode = $currency['code'];
            $levels = $minAmountLevels->get($currencyCode, collect());

            if ($levels->isEmpty()) {
                continue;
            }

            $minAmountStats[$currencyCode] = $this->buildCurrencyStats($currencyCode, $levels);
        }

        return [
            'availableCurrencies' => array_values(array_filter(
                $availableCurrencies,
                fn (array $currency) => isset($minAmountStats[$currency['code']])
            )),
            'minAmountStats' => $minAmountStats,
        ];
    }

    /**
     * @param  Collection<int, int>  $levels
     * @return array<int, array{title: string, min_amount: int|null, count: int, free_limit: string, potential_limit: string}>
     */
    private function buildCurrencyStats(string $currencyCode, Collection $levels): array
    {
        $groups = $levels
            ->map(fn (int $amountUnits) => [
                'title' => 'От '.Money::fromUnits((string) $amountUnits, $currencyCode)->toBeauty(),
                'min_amount' => $amountUnits,
            ])
            ->prepend([
                'title' => 'Не указан',
                'min_amount' => null,
            ])
            ->values();

        return $groups
            ->map(function (array $group) use ($currencyCode) {
                $query = $this->activePaymentDetailsQuery($currencyCode);

                if ($group['min_amount'] === null) {
                    $query->whereNull('min_order_amount');
                } else {
                    $query
                        ->whereNotNull('min_order_amount')
                        ->where('min_order_amount', '<=', $group['min_amount']);
                }

                $count = (clone $query)->count();
                $freeLimit = (string) ((clone $query)->toBase()
                    ->selectRaw('COALESCE(SUM(CAST(daily_limit AS DECIMAL(65, 0)) - CAST(current_daily_limit AS DECIMAL(65, 0))), 0) as free_limit')
                    ->value('free_limit') ?? 0);
                $detailIds = (clone $query)->pluck('id')->toArray();
                $pendingAmount = $this->getPendingAmount($detailIds, $currencyCode);
                $potentialLimit = bcadd($freeLimit, $pendingAmount, 0);

                return [
                    'title' => $group['title'],
                    'min_amount' => $group['min_amount'],
                    'count' => $count,
                    'free_limit' => Money::fromUnits($freeLimit, $currencyCode)->toBeauty(),
                    'potential_limit' => Money::fromUnits($potentialLimit, $currencyCode)->toBeauty(),
                ];
            })
            ->toArray();
    }

    private function activePaymentDetailsQuery(string $currencyCode): Builder
    {
        return PaymentDetail::query()
            ->whereNull('archived_at')
            ->where('is_active', true)
            ->whereRelation('user', 'is_online', true)
            ->where('currency', $currencyCode);
    }

    /**
     * @param  array<int, int>  $detailIds
     */
    private function getPendingAmount(array $detailIds, string $currencyCode): string
    {
        if ($detailIds === []) {
            return '0';
        }

        return (string) (Order::query()
            ->whereIn('payment_detail_id', $detailIds)
            ->where('status', OrderStatus::PENDING)
            ->where('currency', $currencyCode)
            ->toBase()
            ->selectRaw('COALESCE(SUM(CAST(amount AS DECIMAL(65, 0))), 0) as pending_amount')
            ->value('pending_amount') ?? 0);
    }

    /**
     * @return Collection<int, string>
     */
    private function getMerchantGeoCurrencyCodes(User $user): Collection
    {
        return Merchant::query()
            ->where('user_id', $user->id)
            ->get(['settings'])
            ->flatMap(fn (Merchant $merchant) => array_keys($merchant->getGeoMap()))
            ->map(fn (string $currencyCode) => strtolower($currencyCode))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function getConfiguredLevelCurrencyCodes(): Collection
    {
        return EnabledCardMinAmountLevel::query()
            ->select('currency')
            ->distinct()
            ->pluck('currency')
            ->map(fn (string $currencyCode) => strtolower($currencyCode))
            ->unique()
            ->values();
    }
}
