<?php

namespace App\Queries\Interfaces;

use App\Models\User;
use App\ObjectValues\TableFilters\TableFiltersValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CallbackLogQueries
{
    /**
     * Получить пагинированный список логов колбеков для админки
     */
    public function paginateForAdmin(TableFiltersValue $filters): LengthAwarePaginator;

    /**
     * Получить пагинированный список логов колбеков для мерчанта (только свои магазины)
     */
    public function paginateForMerchant(User $user, TableFiltersValue $filters): LengthAwarePaginator;
}
