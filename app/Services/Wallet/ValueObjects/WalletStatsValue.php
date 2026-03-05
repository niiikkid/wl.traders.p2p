<?php

namespace App\Services\Wallet\ValueObjects;

use App\Services\Money\Money;
use Illuminate\Support\Collection;

/**
 * @property Collection<string, Money> $totalAvailableBalances
 * @property Collection<string, Money> $lockedForWithdrawalBalances
 */
class WalletStatsValue extends ValueObject
{
    public function __construct(
        protected BaseValue $base,
        protected Collection $totalAvailableBalances,
        protected Collection $lockedForWithdrawalBalances,
        protected EscrowsValue $escrowBalances,
        protected CurrencyValue $currency,
        protected int $maxReserveBalance,
    )
    {}

    public function toArray(): array
    {
        $result['base'] = [
            'merchantAmount' => $this->base->merchantAmount->toBeauty(),
            'trustAmount' => $this->base->trustAmount->toBeauty(),
            'trustReserveAmount' => $this->base->trustReserveAmount->toBeauty(),
            'teamleaderAmount' => $this->base->teamleaderAmount->toBeauty(),
        ];

        $result['totalAvailableBalances'] = $this->totalAvailableBalances->transform(function (BalanceValue $item) {
            return [
                'primary' => $item->primary->toBeauty(),
                'secondary' => $item->secondary->toBeauty()
            ];
        })->toArray();

        $result['lockedForWithdrawalBalances'] = $this->lockedForWithdrawalBalances->transform(function (BalanceValue $item) {
            return [
                'primary' => $item->primary->toBeauty(),
                'secondary' => $item->secondary->toBeauty()
            ];
        })->toArray();

        $result['escrowBalances'] = array_map(function (EscrowValue $item) {
            return [
                'balance' => [
                    'primary' => $item->balance->primary->toBeauty(),
                    'secondary' => $item->balance->secondary->toBeauty()
                ],
                'count' => $item->count,
            ];
        }, get_object_vars($this->escrowBalances));

        $result['currency'] = [
            'primary' => $this->currency->primary->getCode(),
            'secondary' => $this->currency->secondary->getCode(),
        ];

        $result['maxReserveBalance'] = $this->maxReserveBalance;

        return $result;
    }
}
