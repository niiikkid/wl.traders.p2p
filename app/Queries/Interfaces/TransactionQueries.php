<?php

namespace App\Queries\Interfaces;

use App\Enums\BalanceType;
use App\Enums\TransactionDirection;
use App\Models\Wallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TransactionQueries
{
    public function paginate(Wallet $wallet, ?TransactionDirection $transactionDirection = null, ?BalanceType $balanceType = null): LengthAwarePaginator;
}
