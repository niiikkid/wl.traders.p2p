<?php

namespace App\Queries\Interfaces;

use App\Models\PaymentGateway;
use App\ObjectValues\TableFilters\TableFiltersValue;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PaymentGatewayQueries
{
    /**
     * @return Collection<int, PaymentGateway>
     */
    public function getAllActive(): Collection;

    public function paginateForAdmin(TableFiltersValue $filters): LengthAwarePaginator;

    public function getByCode(string $code): ?PaymentGateway;

    /**
     * @return Collection<int, PaymentGateway>
     */
    public function getByCodesForOrderCreate(array $codes, Money $amount): Collection;

    /**
     * @return Collection<int, PaymentGateway>
     */
    public function getByCurrencyForOrderCreate(Currency $currency, Money $amount): Collection;
}
