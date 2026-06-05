<?php

namespace App\Queries\Interfaces;

use App\Models\Payout\Payout;
use App\Models\User;
use App\ObjectValues\TableFilters\TableFiltersValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface PayoutQueries
{
    /**
     * @return Collection<int, Payout>
     */
    public function getStackForTrader(User $trader): Collection;

    /** Пагинация стакана (query-параметр страницы: stack_page). */
    public function paginateStackForTrader(User $trader, int $perPage = 10, ?int $page = null): LengthAwarePaginator;

    public function countStackForTrader(User $trader, string $currency): int;

    /**
     * @return Collection<int, Payout>
     */
    public function getActiveForTrader(User $trader): Collection;

    public function paginateHistoryForTrader(User $trader): LengthAwarePaginator;

    public function countActiveForTrader(User $trader): int;

    public function paginateForAdmin(TableFiltersValue $filters): LengthAwarePaginator;

    /**
     * Полный список выплат для админ-экспорта с теми же фильтрами, что и в таблице.
     */
    public function queryForAdminExport(TableFiltersValue $filters): Builder;

    public function paginateForMerchant(User $user, TableFiltersValue $filters): LengthAwarePaginator;
}
