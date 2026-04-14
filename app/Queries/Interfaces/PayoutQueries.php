<?php

namespace App\Queries\Interfaces;

use App\Models\Payout\Payout;
use App\Models\User;
use App\ObjectValues\TableFilters\TableFiltersValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PayoutQueries
{
    /**
     * @return Collection<int, Payout>
     */
    public function getStackForTrader(): Collection;

    /** Пагинация стакана (query-параметр страницы: stack_page). */
    public function paginateStackForTrader(int $perPage = 10, ?int $page = null): LengthAwarePaginator;

    /**
     * @return Collection<int, Payout>
     */
    public function getActiveForTrader(User $trader): Collection;

    public function paginateHistoryForTrader(User $trader): LengthAwarePaginator;

    public function countActiveForTrader(User $trader): int;

    public function paginateForAdmin(TableFiltersValue $filters): LengthAwarePaginator;

    public function paginateForMerchant(User $user, TableFiltersValue $filters): LengthAwarePaginator;
}
