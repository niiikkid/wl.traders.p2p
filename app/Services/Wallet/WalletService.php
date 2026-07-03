<?php

namespace App\Services\Wallet;

use App\Contracts\WalletServiceContract;
use App\Enums\BalanceType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\MarketEnum;
use App\Enums\OrderStatus;
use App\Enums\TransactionType;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Services\Wallet\GiveToBalanceHandler\GiveToAgent;
use App\Services\Wallet\GiveToBalanceHandler\GiveToCommission;
use App\Services\Wallet\GiveToBalanceHandler\GiveToMerchant;
use App\Services\Wallet\GiveToBalanceHandler\GiveToProvider;
use App\Services\Wallet\GiveToBalanceHandler\GiveToReserve;
use App\Services\Wallet\GiveToBalanceHandler\GiveToTeamleader;
use App\Services\Wallet\GiveToBalanceHandler\GiveToTrust;
use App\Services\Wallet\TakeFromBalanceHandler\TakeFromAgent;
use App\Services\Wallet\TakeFromBalanceHandler\TakeFromCommission;
use App\Services\Wallet\TakeFromBalanceHandler\TakeFromMerchant;
use App\Services\Wallet\TakeFromBalanceHandler\TakeFromProvider;
use App\Services\Wallet\TakeFromBalanceHandler\TakeFromReserve;
use App\Services\Wallet\TakeFromBalanceHandler\TakeFromTeamleader;
use App\Services\Wallet\TakeFromBalanceHandler\TakeFromTrust;
use App\Services\Wallet\ValueObjects\BalanceValue;
use App\Services\Wallet\ValueObjects\BaseValue;
use App\Services\Wallet\ValueObjects\CurrencyValue;
use App\Services\Wallet\ValueObjects\EscrowsValue;
use App\Services\Wallet\ValueObjects\EscrowValue;
use App\Services\Wallet\ValueObjects\WalletStatsValue;
use App\Utils\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WalletService implements WalletServiceContract
{
    public function getMaxReserveBalance(User $user): int
    {
        if (! is_null($user->reserve_balance_limit)) {
            return (int) $user->reserve_balance_limit;
        }

        return services()->settings()->getDefaultReserveBalanceLimit();
    }

    public function create(User $user, ?Merchant $merchant = null): Wallet
    {
        return Wallet::create([
            'merchant_balance' => 0,
            'trust_balance' => 0,
            'provider_balance' => 0,
            'reserve_balance' => 0,
            'commission_balance' => 0,
            'teamleader_balance' => 0,
            'agent_balance' => 0,
            'user_id' => $user->id,
            'merchant_id' => $merchant?->id,
        ]);
    }

    public function createForMerchant(Merchant $merchant): Wallet
    {
        $merchant->loadMissing('user');

        return $this->create($merchant->user, $merchant);
    }

    public function takeFromBalance(int $walletID, Money $amount, TransactionType $transactionType, BalanceType $balanceType, ?Model $transactionable = null): void
    {
        Transaction::run(function () use ($walletID, $amount, $transactionType, $balanceType, $transactionable) {
            $wallet = Wallet::where('id', $walletID)->lockForUpdate()->first();

            $handler = null;

            if ($balanceType->equals(BalanceType::TRUST)) {
                $handler = new TakeFromTrust;
            } elseif ($balanceType->equals(BalanceType::PROVIDER)) {
                $handler = new TakeFromProvider;
            } elseif ($balanceType->equals(BalanceType::MERCHANT)) {
                $handler = new TakeFromMerchant;
            } elseif ($balanceType->equals(BalanceType::COMMISSION)) {
                $handler = new TakeFromCommission;
            } elseif ($balanceType->equals(BalanceType::TEAMLEADER)) {
                $handler = new TakeFromTeamleader;
            } elseif ($balanceType->equals(BalanceType::AGENT)) {
                $handler = new TakeFromAgent;
            } elseif ($balanceType->equals(BalanceType::RESERVE)) {
                $handler = new TakeFromReserve;
            }

            $handler->handle($wallet, $amount, $transactionType, $transactionable);
        });
    }

    public function giveToBalance(int $walletID, Money $amount, TransactionType $transactionType, BalanceType $balanceType, ?Model $transactionable = null): void
    {
        Transaction::run(function () use ($walletID, $amount, $transactionType, $balanceType, $transactionable) {
            $wallet = Wallet::where('id', $walletID)->lockForUpdate()->first();

            $handler = null;

            if ($balanceType->equals(BalanceType::TRUST)) {
                $handler = new GiveToTrust;
            } elseif ($balanceType->equals(BalanceType::PROVIDER)) {
                $handler = new GiveToProvider;
            } elseif ($balanceType->equals(BalanceType::MERCHANT)) {
                $handler = new GiveToMerchant;
            } elseif ($balanceType->equals(BalanceType::COMMISSION)) {
                $handler = new GiveToCommission;
            } elseif ($balanceType->equals(BalanceType::TEAMLEADER)) {
                $handler = new GiveToTeamleader;
            } elseif ($balanceType->equals(BalanceType::AGENT)) {
                $handler = new GiveToAgent;
            } elseif ($balanceType->equals(BalanceType::RESERVE)) {
                $handler = new GiveToReserve;
            }

            $handler->handle($wallet, $amount, $transactionType, $transactionable);
        });
    }

    public function getTotalAvailableBalance(Wallet $wallet, BalanceType $balanceType): Money
    {
        if ($balanceType->equals(BalanceType::TRUST)) {
            $balanceAmount = $wallet->trust_balance->add($wallet->reserve_balance);
        }
        if ($balanceType->equals(BalanceType::RESERVE)) {
            $balanceAmount = $wallet->reserve_balance;
        }
        if ($balanceType->equals(BalanceType::PROVIDER)) {
            $balanceAmount = $wallet->provider_balance ?? Money::zero('USDT');
        }
        if ($balanceType->equals(BalanceType::MERCHANT)) {
            $balanceAmount = $wallet->merchant_balance;
        }
        if ($balanceType->equals(BalanceType::COMMISSION)) {
            $balanceAmount = $wallet->commission_balance;
        }
        if ($balanceType->equals(BalanceType::TEAMLEADER)) {
            $balanceAmount = $wallet->teamleader_balance;
        }
        if ($balanceType->equals(BalanceType::AGENT)) {
            $balanceAmount = $wallet->agent_balance;
        }

        return $balanceAmount;
    }

    public function getWalletStats(Wallet $wallet): WalletStatsValue
    {
        $wallet->loadMissing('user');

        $primaryCurrency = Currency::USDT(); // Равен валюте $wallet
        $secondaryCurrency = Currency::RUB();

        $userFiatCurrency = $wallet->user->fiat_currency;
        if ($userFiatCurrency && Currency::isCurrency($userFiatCurrency)) {
            $secondaryCurrency = Currency::make($userFiatCurrency);
        }

        $conversionRate = services()->market()->getSellPrice($secondaryCurrency, MarketEnum::BYBIT);

        $totalAvailableBalances = collect();

        foreach (BalanceType::cases() as $balanceType) {
            $balance = $this->getTotalAvailableBalance($wallet, $balanceType);
            $totalAvailableBalances->put($balanceType->value,
                new BalanceValue($balance, $conversionRate->mul($balance))
            );
        }

        // ===

        $lockedForWithdrawalBalances = collect();
        $lockedForWithdrawalSums = Invoice::query()
            ->where('type', InvoiceType::WITHDRAWAL)
            ->where('wallet_id', $wallet->id)
            ->where('status', InvoiceStatus::PENDING)
            ->selectRaw('balance_type, COALESCE(SUM(amount), 0) as amount')
            ->groupBy('balance_type')
            ->toBase()
            ->pluck('amount', 'balance_type');

        foreach (BalanceType::cases() as $balanceType) {
            $balance = Money::fromUnits(
                (int) $lockedForWithdrawalSums->get($balanceType->value, 0),
                $primaryCurrency
            );

            $lockedForWithdrawalBalances->put($balanceType->value,
                new BalanceValue($balance, $conversionRate->mul($balance))
            );
        }

        // ===

        $escrowTotals = Order::query()
            ->leftJoin('disputes', 'disputes.order_id', '=', 'orders.id')
            ->where('orders.status', OrderStatus::PENDING)
            ->whereRelation('paymentDetail', 'user_id', $wallet->user_id)
            ->selectRaw('
                COALESCE(SUM(CASE WHEN disputes.id IS NULL THEN orders.total_profit ELSE 0 END), 0) as orders_balance,
                COUNT(CASE WHEN disputes.id IS NULL THEN 1 END) as orders_count,
                COALESCE(SUM(CASE WHEN disputes.id IS NOT NULL THEN orders.total_profit ELSE 0 END), 0) as disputes_balance,
                COUNT(CASE WHEN disputes.id IS NOT NULL THEN 1 END) as disputes_count
            ')
            ->first();

        $escrowOrdersBalance = Money::fromUnits($escrowTotals?->orders_balance ?? 0, $primaryCurrency);
        $escrowOrdersCount = (int) ($escrowTotals?->orders_count ?? 0);
        $escrowDisputeBalance = Money::fromUnits($escrowTotals?->disputes_balance ?? 0, $primaryCurrency);
        $escrowDisputeCount = (int) ($escrowTotals?->disputes_count ?? 0);

        return new WalletStatsValue(
            base: new BaseValue(
                merchantAmount: $wallet->merchant_balance,
                trustAmount: $wallet->trust_balance,
                trustReserveAmount: $wallet->reserve_balance,
                teamleaderAmount: $wallet->teamleader_balance,
                agentAmount: $wallet->agent_balance
            ),
            totalAvailableBalances: $totalAvailableBalances,
            lockedForWithdrawalBalances: $lockedForWithdrawalBalances,
            escrowBalances: new EscrowsValue(
                orders: new EscrowValue(
                    balance: new BalanceValue($escrowOrdersBalance, $conversionRate->mul($escrowOrdersBalance)),
                    count: $escrowOrdersCount
                ),
                disputes: new EscrowValue(
                    balance: new BalanceValue($escrowDisputeBalance, $conversionRate->mul($escrowDisputeBalance)),
                    count: $escrowDisputeCount
                )
            ),
            currency: new CurrencyValue($primaryCurrency, $secondaryCurrency),
            maxReserveBalance: $this->getMaxReserveBalance($wallet->user)
        );
    }

    public function getMerchantWalletStats(User $user, ?int $merchantID = null): array
    {
        $user->loadMissing('roles');

        $primaryCurrency = Currency::USDT();
        $secondaryCurrency = Currency::RUB();

        $userFiatCurrency = $user->fiat_currency;
        if ($userFiatCurrency && Currency::isCurrency($userFiatCurrency)) {
            $secondaryCurrency = Currency::make($userFiatCurrency);
        }

        $conversionRate = services()->market()->getSellPrice($secondaryCurrency, MarketEnum::BYBIT);
        $wallets = $this->merchantWalletsForUser($user, $merchantID)->get();
        $walletIDs = $wallets->pluck('id')->all();

        $merchantBalance = $wallets->reduce(
            fn (Money $balance, Wallet $wallet): Money => $balance->add($wallet->merchant_balance),
            Money::zero($primaryCurrency->getCode())
        );

        $lockedForWithdrawal = Money::zero($primaryCurrency->getCode());
        if ($walletIDs !== []) {
            $lockedForWithdrawal = Money::fromUnits(
                (string) Invoice::query()
                    ->where('type', InvoiceType::WITHDRAWAL)
                    ->whereIn('wallet_id', $walletIDs)
                    ->where('status', InvoiceStatus::PENDING)
                    ->where('balance_type', BalanceType::MERCHANT)
                    ->sum('amount'),
                $primaryCurrency
            );
        }

        $totalAvailableBalances = collect();
        $lockedForWithdrawalBalances = collect();

        foreach (BalanceType::cases() as $balanceType) {
            $balance = $balanceType->equals(BalanceType::MERCHANT)
                ? $merchantBalance
                : Money::zero($primaryCurrency->getCode());

            $lockedBalance = $balanceType->equals(BalanceType::MERCHANT)
                ? $lockedForWithdrawal
                : Money::zero($primaryCurrency->getCode());

            $totalAvailableBalances->put(
                $balanceType->value,
                new BalanceValue($balance, $conversionRate->mul($balance))
            );
            $lockedForWithdrawalBalances->put(
                $balanceType->value,
                new BalanceValue($lockedBalance, $conversionRate->mul($lockedBalance))
            );
        }

        return (new WalletStatsValue(
            base: new BaseValue(
                merchantAmount: $merchantBalance,
                trustAmount: Money::zero($primaryCurrency->getCode()),
                trustReserveAmount: Money::zero($primaryCurrency->getCode()),
                teamleaderAmount: Money::zero($primaryCurrency->getCode()),
                agentAmount: Money::zero($primaryCurrency->getCode())
            ),
            totalAvailableBalances: $totalAvailableBalances,
            lockedForWithdrawalBalances: $lockedForWithdrawalBalances,
            escrowBalances: new EscrowsValue(
                orders: new EscrowValue(
                    balance: new BalanceValue(Money::zero($primaryCurrency->getCode()), Money::zero($secondaryCurrency->getCode())),
                    count: 0
                ),
                disputes: new EscrowValue(
                    balance: new BalanceValue(Money::zero($primaryCurrency->getCode()), Money::zero($secondaryCurrency->getCode())),
                    count: 0
                )
            ),
            currency: new CurrencyValue($primaryCurrency, $secondaryCurrency),
            maxReserveBalance: 0
        ))->toArray();
    }

    public function getMerchantWalletSummaries(User $user): array
    {
        $merchants = Merchant::query()
            ->with('wallet')
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get();

        $walletIDs = $merchants
            ->pluck('wallet.id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $lockedForWithdrawalSums = $walletIDs === []
            ? collect()
            : Invoice::query()
                ->where('type', InvoiceType::WITHDRAWAL)
                ->whereIn('wallet_id', $walletIDs)
                ->where('status', InvoiceStatus::PENDING)
                ->where('balance_type', BalanceType::MERCHANT)
                ->selectRaw('wallet_id, COALESCE(SUM(amount), 0) as amount')
                ->groupBy('wallet_id')
                ->toBase()
                ->pluck('amount', 'wallet_id');

        return $merchants
            ->map(function (Merchant $merchant) use ($lockedForWithdrawalSums): array {
                $wallet = $merchant->wallet;
                $lockedForWithdrawal = Money::fromUnits(
                    (string) $lockedForWithdrawalSums->get($wallet?->id, 0),
                    Currency::USDT()
                );
                $balance = $wallet?->merchant_balance ?? Money::zero(Currency::USDT()->getCode());

                return [
                    'id' => $merchant->id,
                    'uuid' => $merchant->uuid,
                    'name' => $merchant->name,
                    'wallet_id' => $wallet?->id,
                    'balance' => $balance->toBeauty(),
                    'available_balance' => $balance->toBeauty(),
                    'locked_for_withdrawal' => $lockedForWithdrawal->toBeauty(),
                    'currency' => Currency::USDT()->getCode(),
                    'wallet_missing' => $wallet === null,
                ];
            })
            ->values()
            ->all();
    }

    private function merchantWalletsForUser(User $user, ?int $merchantID = null): Builder
    {
        return Wallet::query()
            ->where('user_id', $user->id)
            ->whereNotNull('merchant_id')
            ->when($merchantID !== null, function ($query) use ($merchantID): void {
                $query->where('merchant_id', $merchantID);
            });
    }
}
