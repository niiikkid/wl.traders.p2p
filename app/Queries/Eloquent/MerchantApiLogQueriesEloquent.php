<?php

namespace App\Queries\Eloquent;

use App\Models\MerchantApiRequestLog;
use App\Models\User;
use App\ObjectValues\TableFilters\TableFiltersValue;
use App\Queries\Interfaces\MerchantApiLogQueries;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class MerchantApiLogQueriesEloquent implements MerchantApiLogQueries
{
    public function paginateForAdmin(TableFiltersValue $filters, string $requestType): LengthAwarePaginator
    {
        return MerchantApiRequestLog::query()
            ->with(['merchant', 'order', 'payout'])
            ->tap(fn (Builder $query) => $this->applyRequestTypeFilter($query, $requestType))
            ->when($filters->merchant, function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->whereRelation('merchant', 'name', 'LIKE', '%'.$filters->merchant.'%');
                    $query->orWhereRelation('merchant', 'uuid', 'LIKE', '%'.$filters->merchant.'%');
                });
            })
            ->when($filters->externalID, function ($query) use ($filters) {
                $query->where('external_id', 'LIKE', '%'.$filters->externalID.'%');
            })
            ->when($filters->minAmount, function ($query) use ($filters) {
                $query->where('amount', '>=', (int) $filters->minAmount);
            })
            ->when($filters->maxAmount, function ($query) use ($filters) {
                $query->where('amount', '<=', (int) $filters->maxAmount);
            })
            ->when($filters->currency, function ($query) use ($filters) {
                $query->where('currency', 'LIKE', '%'.$filters->currency.'%');
            })
            ->when($filters->method, function ($query) use ($filters) {
                $query->where('payment_gateway', 'LIKE', '%'.$filters->method.'%');
            })
            ->when($filters->uuid, function ($query) use ($filters, $requestType) {
                $relation = $requestType === MerchantApiRequestLog::TYPE_PAYOUT ? 'payout' : 'order';

                $query->whereRelation($relation, 'uuid', 'LIKE', '%'.$filters->uuid.'%');
            })
            ->when(! empty($filters->apiLogStatuses), function ($query) use ($filters) {
                $query->whereIn('is_successful', $filters->apiLogStatuses);
            })
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);
    }

    public function paginateForMerchant(User $user, TableFiltersValue $filters, string $requestType): LengthAwarePaginator
    {
        return MerchantApiRequestLog::query()
            ->with(['merchant', 'order', 'payout'])
            ->tap(fn (Builder $query) => $this->applyRequestTypeFilter($query, $requestType))
            ->whereRelation('merchant', 'user_id', $user->id)
            ->when($filters->externalID, function ($query) use ($filters) {
                $query->where('external_id', 'LIKE', '%'.$filters->externalID.'%');
            })
            ->when($filters->minAmount, function ($query) use ($filters) {
                $query->where('amount', '>=', (int) $filters->minAmount);
            })
            ->when($filters->maxAmount, function ($query) use ($filters) {
                $query->where('amount', '<=', (int) $filters->maxAmount);
            })
            ->when($filters->currency, function ($query) use ($filters) {
                $query->where('currency', 'LIKE', '%'.$filters->currency.'%');
            })
            ->when($filters->method, function ($query) use ($filters) {
                $query->where('payment_gateway', 'LIKE', '%'.$filters->method.'%');
            })
            ->when($filters->uuid, function ($query) use ($filters, $requestType) {
                $relation = $requestType === MerchantApiRequestLog::TYPE_PAYOUT ? 'payout' : 'order';

                $query->whereRelation($relation, 'uuid', 'LIKE', '%'.$filters->uuid.'%');
            })
            ->when(! empty($filters->apiLogStatuses), function ($query) use ($filters) {
                $query->whereIn('is_successful', $filters->apiLogStatuses);
            })
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);
    }

    private function applyRequestTypeFilter(Builder $query, string $requestType): void
    {
        if ($requestType === MerchantApiRequestLog::TYPE_PAYOUT) {
            $query->where('request_type', MerchantApiRequestLog::TYPE_PAYOUT);

            return;
        }

        $query->where(function (Builder $query): void {
            $query
                ->where('request_type', MerchantApiRequestLog::TYPE_ORDER)
                ->orWhereNull('request_type');
        });
    }
}
