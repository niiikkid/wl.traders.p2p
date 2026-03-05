<?php

namespace App\Services\Wallet\GiveToBalanceHandler;

use App\Enums\TransactionType;
use App\Models\Wallet;
use App\Services\Money\Money;

abstract class GiveToBalance
{
    abstract public function handle(Wallet $wallet, Money $amount, TransactionType $transactionType): void;
}
