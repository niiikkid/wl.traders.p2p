<?php

namespace App\Services\Wallet\TakeFromBalanceHandler;

use App\Enums\BalanceType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Exceptions\WalletException;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Money\Money;

class TakeFromTrust extends TakeFromBalance
{
    public function handle(Wallet $wallet, Money $amount, TransactionType $transactionType): void
    {
        if ($transactionType->direction()->notEquals(TransactionDirection::OUT)) {
            throw WalletException::invalidTransactionTypeForTake();
        }

        $trust = $wallet->trust_balance->sub($amount);

        if ($trust->lessThanZero()) {
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
        ]);
    }
}
