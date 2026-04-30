<?php

namespace App\Queries\Interfaces;

use App\Models\User;
use App\ObjectValues\TableFilters\TableFiltersValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MerchantApiLogQueries
{
    public function paginateForAdmin(TableFiltersValue $filters): LengthAwarePaginator;

    public function paginateForMerchant(User $user, TableFiltersValue $filters): LengthAwarePaginator;
}
