<?php

namespace App\Queries\Interfaces;

use App\ObjectValues\TableFilters\TableFiltersValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CallbackLogQueries
{
    /**
     * Получить пагинированный список логов колбеков для админки
     *
     * @param TableFiltersValue $filters
     * @return LengthAwarePaginator
     */
    public function paginateForAdmin(TableFiltersValue $filters): LengthAwarePaginator;
} 