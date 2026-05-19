<?php

namespace App\Queries\Interfaces;

use App\Models\User;
use App\ObjectValues\TableFilters\TableFiltersValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MerchantApiLogQueries
{
    public function paginateForAdmin(TableFiltersValue $filters, string $requestType): LengthAwarePaginator;

    public function paginateForMerchant(User $user, TableFiltersValue $filters, string $requestType): LengthAwarePaginator;
}
