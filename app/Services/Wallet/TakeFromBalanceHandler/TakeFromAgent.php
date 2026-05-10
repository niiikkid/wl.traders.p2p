<?php

namespace App\Services\Wallet\TakeFromBalanceHandler;

use App\Enums\BalanceType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Money\Money;
use Illuminate\Database\Eloquent\Model;

class TakeFromAgent extends TakeFromBalance
{
    public function handle(Wallet $wallet, Money $amount, TransactionType $transactionType, ?Model $transactionable = null): void
    {
        $balance = $wallet->agent_balance->sub($amount);

        $wallet->update([
            'agent_balance' => $balance,
        ]);

        Transaction::create([
            'amount' => $amount,
            'direction' => TransactionDirection::OUT,
            'type' => $transactionType,
            'balance_type' => BalanceType::AGENT,
            'wallet_id' => $wallet->id,
            'transactionable_id' => $transactionable?->getKey(),
            'transactionable_type' => $transactionable?->getMorphClass(),
        ]);
    }
}
