<?php

declare(strict_types=1);

namespace App\Services\Wallet\TakeFromBalanceHandler;

use App\Enums\BalanceType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Exceptions\WalletException;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Money\Money;
use Illuminate\Database\Eloquent\Model;

class TakeFromReserve extends TakeFromBalance
{
    public function handle(Wallet $wallet, Money $amount, TransactionType $transactionType, ?Model $transactionable = null): void
    {
        if ($transactionType->direction()->notEquals(TransactionDirection::OUT)) {
            throw WalletException::invalidTransactionTypeForTake();
        }

        $balance = $wallet->reserve_balance->sub($amount);

        if ($balance->lessThanZero()) {
            throw WalletException::insufficientFunds();
        }

        $wallet->update([
            'reserve_balance' => $balance,
        ]);

        Transaction::create([
            'amount' => $amount,
            'direction' => TransactionDirection::OUT,
            'type' => $transactionType,
            'balance_type' => BalanceType::RESERVE,
            'wallet_id' => $wallet->id,
            'transactionable_id' => $transactionable?->getKey(),
            'transactionable_type' => $transactionable?->getMorphClass(),
        ]);
    }
}
