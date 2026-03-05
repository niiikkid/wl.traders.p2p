<?php

namespace App\Queries\Interfaces;

use App\Models\User;
use App\ObjectValues\TableFilters\TableFiltersValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaymentDetailQueries
{
    public function paginateForAdmin(TableFiltersValue $filters, bool $fromArchive = false): LengthAwarePaginator;

    public function paginateForUser(User $user, TableFiltersValue $filters, bool $fromArchive = false): LengthAwarePaginator;
}
