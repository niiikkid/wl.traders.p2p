<?php

namespace App\Queries\Eloquent;

use App\Enums\BalanceType;
use App\Enums\TransactionDirection;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Queries\Interfaces\InvoiceQueries;
use App\Queries\Interfaces\TransactionQueries;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TransactionQueriesEloquent implements TransactionQueries
{
    public function paginate(Wallet $wallet, ?TransactionDirection $transactionDirection = null, ?BalanceType $balanceType = null): LengthAwarePaginator
    {
        return Transaction::query()
            ->where('wallet_id', $wallet->id)
            ->when($transactionDirection, function ($query) use ($transactionDirection) {
                return $query->where('direction', $transactionDirection);
            })
            ->when($balanceType, function ($query) use ($balanceType) {
                return $query->where('balance_type', $balanceType);
            })
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);
    }
}
