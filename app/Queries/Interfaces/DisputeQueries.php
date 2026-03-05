<?php

namespace App\Queries\Interfaces;

use App\Models\User;
use App\ObjectValues\TableFilters\TableFiltersValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DisputeQueries
{
    public function paginateForAdmin(TableFiltersValue $filters): LengthAwarePaginator;

    public function paginateForUser(User $user, TableFiltersValue $filters): LengthAwarePaginator;
}
