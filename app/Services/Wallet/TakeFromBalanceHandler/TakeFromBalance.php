<?php

namespace App\Services\Wallet\TakeFromBalanceHandler;

use App\Enums\TransactionType;
use App\Models\Wallet;
use App\Services\Money\Money;
use Illuminate\Database\Eloquent\Model;

abstract class TakeFromBalance
{
    abstract public function handle(Wallet $wallet, Money $amount, TransactionType $transactionType, ?Model $transactionable = null): void;
}
