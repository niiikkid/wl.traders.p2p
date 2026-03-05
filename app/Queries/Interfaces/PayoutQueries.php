<?php

namespace App\Queries\Interfaces;

use App\Models\User;
use App\ObjectValues\TableFilters\TableFiltersValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PayoutQueries
{
    /**
     * @return Collection<int, \App\Models\Payout\Payout>
     */
    public function getStackForTrader(): Collection;

    /**
     * @return Collection<int, \App\Models\Payout\Payout>
     */
    public function getActiveForTrader(User $trader): Collection;

    public function paginateHistoryForTrader(User $trader, int $perPage = 15): LengthAwarePaginator;

    public function countActiveForTrader(User $trader): int;

    public function paginateForAdmin(TableFiltersValue $filters): LengthAwarePaginator;

    public function paginateForMerchant(User $user, TableFiltersValue $filters): LengthAwarePaginator;
}


