<?php

declare(strict_types=1);

/**
 * Каскад: залог ликвидности внешнего провайдера (USDT).
 */

namespace App\Services\Cascade;

use App\Enums\BalanceType;
use App\Enums\CascadeDealEventType;
use App\Enums\FundsOnHoldStatus;
use App\Enums\ProviderType;
use App\Enums\TransactionType;
use App\Exceptions\CascadeException;
use App\Models\CascadeDeal;
use App\Models\CascadeProvider;
use App\Models\FundsOnHold;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Utils\Transaction;

/**
 * Удерживает и освобождает залог провайдера через {@see FundsOnHold} и движения по кошельку (внутренние провайдеры не затрагиваются).
 */
class CascadeProviderCollateralService
{
    public function __construct(
        private readonly CascadeDealEventRecorder $events,
    ) {}

    public function holdForWinner(CascadeDeal $deal, CascadeProvider $provider): ?FundsOnHold
    {
        if ($provider->provider_type->equals(ProviderType::INTERNAL)) {
            return null;
        }

        $provider->loadMissing('user.wallet');
        $wallet = $provider->user?->wallet;

        if (! $wallet) {
            throw CascadeException::make('У внешнего провайдера не привязан пользователь Provider Liquidity с кошельком.');
        }

        $amount = $deal->usdt_amount;

        if (! $amount instanceof Money || $amount->getCurrency()->notEquals(Currency::USDT())) {
            throw CascadeException::make('Для удержания залога провайдера не рассчитано обязательство в USDT.');
        }

        return Transaction::run(function () use ($deal, $provider, $wallet, $amount): FundsOnHold {
            services()->wallet()->takeFromBalance(
                walletID: $wallet->id,
                amount: $amount,
                transactionType: TransactionType::CASCADE_PROVIDER_COLLATERAL_HOLD,
                balanceType: BalanceType::PROVIDER,
                transactionable: $deal,
            );

            $hold = FundsOnHold::query()->create([
                'amount' => $amount,
                'currency' => $amount->getCurrency(),
                'source_wallet_id' => $wallet->id,
                'source_wallet_balance_type' => BalanceType::PROVIDER,
                'destination_wallet_id' => null,
                'destination_wallet_balance_type' => null,
                'holdable_id' => $deal->id,
                'holdable_type' => $deal->getMorphClass(),
                'status' => FundsOnHoldStatus::TIMER_NOT_SET,
            ]);

            $this->events->record(
                deal: $deal,
                type: CascadeDealEventType::COLLATERAL_CHANGED,
                payload: [
                    'action' => 'hold',
                    'amount' => $amount->toBeauty(),
                    'currency' => $amount->getCurrency()->getCode(),
                    'funds_on_hold_id' => $hold->id,
                ],
                provider: $provider,
            );

            return $hold;
        });
    }

    public function release(FundsOnHold $hold): FundsOnHold
    {
        return Transaction::run(function () use ($hold): FundsOnHold {
            if ($hold->status->notEqualsAny([FundsOnHoldStatus::TIMER_NOT_SET, FundsOnHoldStatus::PENDING_FOR_EXECUTION])) {
                throw CascadeException::make('Удержание залога уже закрыто.');
            }

            services()->wallet()->giveToBalance(
                walletID: $hold->source_wallet_id,
                amount: $hold->amount,
                transactionType: TransactionType::CASCADE_PROVIDER_COLLATERAL_RELEASE,
                balanceType: $hold->source_wallet_balance_type,
                transactionable: $hold->holdable,
            );

            $hold->update(['status' => FundsOnHoldStatus::CANCELED]);

            if ($hold->holdable instanceof CascadeDeal) {
                $this->events->record(
                    deal: $hold->holdable,
                    type: CascadeDealEventType::COLLATERAL_CHANGED,
                    payload: [
                        'action' => 'release',
                        'amount' => $hold->amount->toBeauty(),
                        'currency' => $hold->amount->getCurrency()->getCode(),
                        'funds_on_hold_id' => $hold->id,
                    ],
                );
            }

            return $hold->refresh();
        });
    }

    public function holdCurrentAmount(CascadeDeal $deal, CascadeProvider $provider): ?FundsOnHold
    {
        if ($provider->provider_type->equals(ProviderType::INTERNAL)) {
            return null;
        }

        return $this->replaceForAmountChange($deal, $provider);
    }

    public function releaseActiveForDeal(CascadeDeal $deal): void
    {
        Transaction::run(function () use ($deal): void {
            $deal->collateralHolds()
                ->whereIn('status', [FundsOnHoldStatus::TIMER_NOT_SET->value, FundsOnHoldStatus::PENDING_FOR_EXECUTION->value])
                ->lockForUpdate()
                ->get()
                ->each(fn (FundsOnHold $hold): FundsOnHold => $this->release($hold));
        });
    }

    public function replaceForAmountChange(CascadeDeal $deal, CascadeProvider $provider): ?FundsOnHold
    {
        if ($provider->provider_type->equals(ProviderType::INTERNAL)) {
            return null;
        }

        $amount = $deal->usdt_amount;

        if (! $amount instanceof Money || $amount->getCurrency()->notEquals(Currency::USDT())) {
            throw CascadeException::make('Для обновления залога провайдера не рассчитано обязательство в USDT.');
        }

        return Transaction::run(function () use ($deal, $provider, $amount): FundsOnHold {
            $activeHolds = $deal->collateralHolds()
                ->whereIn('status', [FundsOnHoldStatus::TIMER_NOT_SET->value, FundsOnHoldStatus::PENDING_FOR_EXECUTION->value])
                ->lockForUpdate()
                ->get();

            if ($activeHolds->count() === 1 && $activeHolds->first()->amount->equals($amount)) {
                return $activeHolds->first();
            }

            $activeHolds->each(fn (FundsOnHold $hold): FundsOnHold => $this->release($hold));

            return $this->holdForWinner($deal, $provider);
        });
    }

    public function markReconciled(FundsOnHold $hold): FundsOnHold
    {
        return Transaction::run(function () use ($hold): FundsOnHold {
            $hold->update(['status' => FundsOnHoldStatus::COMPLETED]);

            if ($hold->holdable instanceof CascadeDeal) {
                $this->events->record(
                    deal: $hold->holdable,
                    type: CascadeDealEventType::COLLATERAL_CHANGED,
                    payload: [
                        'action' => 'reconciled',
                        'amount' => $hold->amount->toBeauty(),
                        'currency' => $hold->amount->getCurrency()->getCode(),
                        'funds_on_hold_id' => $hold->id,
                    ],
                );
            }

            return $hold->refresh();
        });
    }
}
