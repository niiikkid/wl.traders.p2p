<?php

namespace App\Services\Wallet\TakeFromBalanceHandler;

use App\Enums\BalanceType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Exceptions\WalletException;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Money\Money;
use App\Services\Notification\Events\TrustBalanceLowNotificationEvent;
use Illuminate\Database\Eloquent\Model;

class TakeFromTrust extends TakeFromBalance
{
    public function handle(Wallet $wallet, Money $amount, TransactionType $transactionType, ?Model $transactionable = null): void
    {
        if ($transactionType->direction()->notEquals(TransactionDirection::OUT)) {
            throw WalletException::invalidTransactionTypeForTake();
        }

        $wallet->loadMissing('user');
        $previousTrustBalance = $wallet->trust_balance;
        $trust = $previousTrustBalance->sub($amount);
        $currentTrustBalance = $trust;

        if ($trust->lessThanZero()) {
            if ($transactionType->equals(TransactionType::TRANSFER_TO_TRADER)) {
                throw WalletException::insufficientFunds();
            }

            $currentTrustBalance = Money::fromUnits(0, $trust->getCurrency()->getCode());
            $wallet->update([
                'trust_balance' => 0,
                'reserve_balance' => $wallet->reserve_balance->sub($trust->abs()),
            ]);
        } else {
            $wallet->update([
                'trust_balance' => $trust,
            ]);
        }

        Transaction::create([
            'amount' => $amount,
            'direction' => TransactionDirection::OUT,
            'type' => $transactionType,
            'balance_type' => BalanceType::TRUST,
            'wallet_id' => $wallet->id,
            'transactionable_id' => $transactionable?->getKey(),
            'transactionable_type' => $transactionable?->getMorphClass(),
        ]);

        if ($wallet->user) {
            services()->notification()->dispatch(new TrustBalanceLowNotificationEvent(
                trader: $wallet->user,
                previousBalance: $previousTrustBalance,
                currentBalance: $currentTrustBalance
            ));
        }
    }
}
