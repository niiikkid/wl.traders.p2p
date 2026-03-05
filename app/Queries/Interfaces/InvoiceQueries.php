<?php

namespace App\Queries\Interfaces;

use App\Enums\BalanceType;
use App\Enums\InvoiceType;
use App\Models\Wallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InvoiceQueries
{
    public function paginate(Wallet $wallet, ?InvoiceType $invoiceType = null, ?BalanceType $balanceType = null): LengthAwarePaginator;
}
