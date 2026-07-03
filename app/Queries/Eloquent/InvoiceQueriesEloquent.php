<?php

namespace App\Queries\Eloquent;

use App\Enums\BalanceType;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Wallet;
use App\Queries\Interfaces\InvoiceQueries;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InvoiceQueriesEloquent implements InvoiceQueries
{
    public function paginate(Wallet $wallet, ?InvoiceType $invoiceType = null, ?BalanceType $balanceType = null): LengthAwarePaginator
    {
        return $this->baseQuery([$wallet->id], $invoiceType, $balanceType);
    }

    public function paginateForWalletIds(array $walletIds, ?InvoiceType $invoiceType = null, ?BalanceType $balanceType = null): LengthAwarePaginator
    {
        return $this->baseQuery($walletIds, $invoiceType, $balanceType);
    }

    private function baseQuery(array $walletIds, ?InvoiceType $invoiceType = null, ?BalanceType $balanceType = null): LengthAwarePaginator
    {
        return Invoice::query()
            ->with(['wallet.user', 'wallet.merchant', 'withdrawalAddress'])
            ->whereIn('wallet_id', $walletIds)
            ->when($invoiceType, function ($query) use ($invoiceType) {
                return $query->where('type', $invoiceType);
            })
            ->when($balanceType, function ($query) use ($balanceType) {
                return $query->where('balance_type', $balanceType);
            })
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);
    }
}
