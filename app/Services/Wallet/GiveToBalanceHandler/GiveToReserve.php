<?php

declare(strict_types=1);

namespace App\Services\Wallet\GiveToBalanceHandler;

use App\Enums\BalanceType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Exceptions\WalletException;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Money\Money;
use Illuminate\Database\Eloquent\Model;

class GiveToReserve extends GiveToBalance
{
    public function handle(Wallet $wallet, Money $amount, TransactionType $transactionType, ?Model $transactionable = null): void
    {
        if ($transactionType->direction()->notEquals(TransactionDirection::IN)) {
            throw WalletException::invalidTransactionTypeForGive();
        }

        $wallet->update([
            'reserve_balance' => $wallet->reserve_balance->add($amount),
        ]);

        Transaction::create([
            'amount' => $amount,
            'direction' => TransactionDirection::IN,
            'type' => $transactionType,
            'balance_type' => BalanceType::RESERVE,
            'wallet_id' => $wallet->id,
            'transactionable_id' => $transactionable?->getKey(),
            'transactionable_type' => $transactionable?->getMorphClass(),
        ]);
    }
}
