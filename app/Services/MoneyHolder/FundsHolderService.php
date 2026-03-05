<?php

namespace App\Services\MoneyHolder;

use App\Contracts\FundsHolderServiceContract;
use App\Enums\BalanceType;
use App\Enums\FundsOnHoldStatus;
use App\Enums\TransactionType;
use App\Exceptions\FundsHolderException;
use App\Models\FundsOnHold;
use App\Models\Wallet;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class FundsHolderService implements FundsHolderServiceContract
{
    public function holdFundsFor(
        Money $amount,
        Wallet $sourceWallet,
        ?Wallet $destinationWallet,
        BalanceType $sourceWalletBalanceType,
        ?BalanceType $destinationWalletBalanceType,
        Model $forAction,
        ?Carbon $until = null,
    ): FundsOnHold
    {
        if ($amount->getCurrency()->notEquals(Currency::USDT())) {
            throw FundsHolderException::invalidAmountCurrency();
        }

        //TODO validate insufficient funds

        services()->wallet()->takeFromBalance(
            walletID: $sourceWallet->id,
            amount: $amount,
            transactionType: TransactionType::PAYMENT_FOR_OPENED_ORDER,
            balanceType: $sourceWalletBalanceType,
        );

        $fundsOnHold = FundsOnHold::create([
            'amount' => $amount,
            'currency' => $amount->getCurrency(),
            'source_wallet_id' => $sourceWallet->id,
            'source_wallet_balance_type' => $sourceWalletBalanceType,
            'destination_wallet_id' => $destinationWallet?->id,
            'destination_wallet_balance_type' => $destinationWalletBalanceType,
            'hold_until' => $until,
            'status' => $until
                ? FundsOnHoldStatus::PENDING_FOR_EXECUTION
                : FundsOnHoldStatus::TIMER_NOT_SET,
        ]);

        $fundsOnHold->holdable()->associate($forAction)->save();

        return $fundsOnHold;
    }

    public function setTimer(FundsOnHold $fundsOnHold, Carbon $timer): void
    {
        if ($fundsOnHold->status->notEquals(FundsOnHoldStatus::TIMER_NOT_SET)) {
            throw FundsHolderException::invalidStatus();
        }

        $fundsOnHold->update([
            'hold_until' => $timer,
            'status' => FundsOnHoldStatus::PENDING_FOR_EXECUTION ,
        ]);
    }

    public function changeDestination(
        FundsOnHold $fundsOnHold,
        ?Wallet $destinationWallet,
        ?BalanceType $destinationWalletBalanceType
    ): FundsOnHold
    {
        if ($fundsOnHold->status->notEquals(FundsOnHoldStatus::TIMER_NOT_SET)) {
            throw FundsHolderException::invalidStatus();
        }

        $fundsOnHold->update([
            'destination_wallet_id' => $destinationWallet?->id,
            'destination_wallet_balance_type' => $destinationWalletBalanceType,
        ]);

        return $fundsOnHold;
    }

    public function execute(FundsOnHold $fundsOnHold): FundsOnHold
    {
        if ($fundsOnHold->hold_until->greaterThan(now())) {
            throw FundsHolderException::timerIsNotUpYet();
        }

        if ($fundsOnHold->status->notEquals(FundsOnHoldStatus::PENDING_FOR_EXECUTION)) {
            throw FundsHolderException::invalidStatus();
        }

        if (! $fundsOnHold->destinationWallet || ! $fundsOnHold->destination_wallet_balance_type) {
            throw FundsHolderException::invalidStatus();
        }

        services()->wallet()->giveToBalance(
            walletID: $fundsOnHold->destinationWallet->id,
            amount: $fundsOnHold->amount,
            transactionType: TransactionType::INCOME_FROM_A_SUCCESSFUL_ORDER,
            balanceType: $fundsOnHold->destination_wallet_balance_type
        );

        $fundsOnHold->update([
            'status' => FundsOnHoldStatus::COMPLETED,
        ]);

        return $fundsOnHold;
    }

    public function cancel(FundsOnHold $fundsOnHold): FundsOnHold
    {
        if ($fundsOnHold->status->notEqualsAny([FundsOnHoldStatus::TIMER_NOT_SET, FundsOnHoldStatus::PENDING_FOR_EXECUTION])) {
            throw FundsHolderException::invalidStatus();
        }

        services()->wallet()->giveToBalance(
            walletID: $fundsOnHold->sourceWallet->id,
            amount: $fundsOnHold->amount,
            transactionType: TransactionType::REFUND_FOR_CANCELED_ORDER,
            balanceType: $fundsOnHold->source_wallet_balance_type
        );

        $fundsOnHold->update([
            'status' => FundsOnHoldStatus::CANCELED,
        ]);

        return $fundsOnHold;
    }
}
