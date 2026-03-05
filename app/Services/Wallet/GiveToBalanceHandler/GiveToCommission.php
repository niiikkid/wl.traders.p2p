<?php

namespace App\Services\Wallet\GiveToBalanceHandler;

use App\Enums\BalanceType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Money\Money;

class GiveToCommission extends GiveToBalance
{
    public function handle(Wallet $wallet, Money $amount, TransactionType $transactionType): void
    {
        $balance = $wallet->commission_balance->add($amount);

        $wallet->update([
            'commission_balance' => $balance,
        ]);

        Transaction::create([
            'amount' => $amount,
            'direction' => TransactionDirection::IN,
            'type' => $transactionType,
            'balance_type' => BalanceType::COMMISSION,
            'wallet_id' => $wallet->id,
        ]);
    }
}
