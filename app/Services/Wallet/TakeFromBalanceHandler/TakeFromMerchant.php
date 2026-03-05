<?php

namespace App\Services\Wallet\TakeFromBalanceHandler;

use App\Enums\BalanceType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Money\Money;

class TakeFromMerchant extends TakeFromBalance
{
    public function handle(Wallet $wallet, Money $amount, TransactionType $transactionType): void
    {
        $balance = $wallet->merchant_balance->sub($amount);

        $wallet->update([
            'merchant_balance' => $balance,
        ]);

        Transaction::create([
            'amount' => $amount,
            'direction' => TransactionDirection::OUT,
            'type' => $transactionType,
            'balance_type' => BalanceType::MERCHANT,
            'wallet_id' => $wallet->id,
        ]);
    }
}
