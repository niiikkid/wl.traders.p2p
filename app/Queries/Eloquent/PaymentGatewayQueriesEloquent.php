<?php

namespace App\Queries\Eloquent;

use App\Models\PaymentGateway;
use App\ObjectValues\TableFilters\TableFiltersValue;
use App\Queries\Interfaces\PaymentGatewayQueries;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PaymentGatewayQueriesEloquent implements PaymentGatewayQueries
{
    /**
     * @return Collection<int, PaymentGateway>
     */
    public function getAllActive(): Collection
    {
        return PaymentGateway::query()->active()->get();
    }

    public function paginateForAdmin(TableFiltersValue $filters): LengthAwarePaginator
    {
        $currencyCodes = array_values(array_unique(array_filter(array_map(
            static fn (string $value) => strtolower(trim($value)),
            explode(',', (string) ($filters->currency ?? ''))
        ))));

        return PaymentGateway::query()
            ->when($filters->search, function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->where('name', 'like', '%' . $filters->search . '%');
                    $query->orWhere('code', 'like', '%' . $filters->search . '%');
                });
            })
            ->when(! empty($currencyCodes), function ($query) use ($currencyCodes) {
                $query->whereIn('currency', $currencyCodes);
            })
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);
    }

    public function getByCode(string $code): ?PaymentGateway
    {
        return PaymentGateway::where('code', $code)->active()->first();
    }

    /**
     * @return Collection<int, PaymentGateway>
     */
    public function getByCodesForOrderCreate(array $codes, Money $amount): Collection
    {
        return PaymentGateway::query()
            ->where(function ($query) use ($amount) {
                $query->where('min_limit', '<=', intval($amount->toBeauty()));
                $query->where('max_limit', '>=', intval($amount->toBeauty()));
            })
            ->whereIn('code', $codes)
            ->active()
            ->get();
    }

    /**
     * @return Collection<int, PaymentGateway>
     */
    public function getByCurrencyForOrderCreate(Currency $currency, Money $amount): Collection
    {
        return PaymentGateway::query()
            ->where(function ($query) use ($amount) {
                $query->where('min_limit', '<=', intval($amount->toBeauty()));
                $query->where('max_limit', '>=', intval($amount->toBeauty()));
            })
            ->where('currency', $currency->getCode())
            ->active()
            ->get();
    }
}
