<?php

namespace App\Services\Wallet\TakeFromBalanceHandler;

use App\Enums\TransactionType;
use App\Models\Wallet;
use App\Services\Money\Money;

abstract class TakeFromBalance
{
    abstract public function handle(Wallet $wallet, Money $amount, TransactionType $transactionType): void;
}
