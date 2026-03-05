<?php

namespace App\Services\Wallet\GiveToBalanceHandler;

use App\Enums\BalanceType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Money\Money;

class GiveToMerchant extends GiveToBalance
{
    public function handle(Wallet $wallet, Money $amount, TransactionType $transactionType): void
    {
        $balance = $wallet->merchant_balance->add($amount);

        $wallet->update([
            'merchant_balance' => $balance,
        ]);

        Transaction::create([
            'amount' => $amount,
            'direction' => TransactionDirection::IN,
            'type' => $transactionType,
            'balance_type' => BalanceType::MERCHANT,
            'wallet_id' => $wallet->id,
        ]);
    }
}
