<?php

namespace App\Queries\Eloquent;

use App\Models\Dispute;
use App\Models\User;
use App\ObjectValues\TableFilters\TableFiltersValue;
use App\Queries\Interfaces\DisputeQueries;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DisputeQueriesEloquent implements DisputeQueries
{
    public function paginateForAdmin(TableFiltersValue $filters): LengthAwarePaginator
    {
        return Dispute::query()
            ->with(['order.paymentDetail.user', 'order.paymentGateway'])
            ->when(! empty($filters->disputeStatuses), function ($query) use ($filters) {
                $query->whereIn('status', $filters->disputeStatuses);
            })
            ->when($filters->uuid, function ($query) use ($filters) {
                $query->whereRelation('order', 'uuid', 'LIKE', '%' . $filters->uuid . '%');
            })
            ->when($filters->amount, function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $amount = Money::fromPrecision($filters->amount, Currency::USDT())->toUnits();
                    $query->whereRelation('order', 'amount', 'LIKE', '%' . $amount . '%');
                    $query->orWhereRelation('order', 'total_profit', 'LIKE', '%' . $amount . '%');
                });
            })
            ->when($filters->paymentDetail, function ($query) use ($filters) {
                $query->whereRelation('order.paymentDetail', 'detail', 'LIKE', '%' . $filters->paymentDetail . '%');
            })
            ->when($filters->user, function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->whereRelation('order.paymentDetail.user', 'name', 'LIKE', '%' . $filters->user . '%');
                    $query->orWhereRelation('order.paymentDetail.user', 'email', 'LIKE', '%' . $filters->user . '%');
                });
            })
            ->when($filters->externalID, function ($query) use ($filters) {
                $query->whereRelation('order', 'external_id', 'LIKE', '%' . $filters->externalID . '%');
            })
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);
    }

    public function paginateForUser(User $user, TableFiltersValue $filters): LengthAwarePaginator
    {
        return Dispute::query()
            ->whereRelation('order.paymentDetail', 'user_id', auth()->user()->id)
            ->when(! empty($filters->disputeStatuses), function ($query) use ($filters) {
                $query->whereIn('status', $filters->disputeStatuses);
            })
            ->when($filters->uuid, function ($query) use ($filters) {
                $query->whereRelation('order', 'uuid', 'LIKE', '%' . $filters->uuid . '%');
            })
            ->when($filters->amount, function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $amount = Money::fromPrecision($filters->amount, Currency::USDT())->toUnits();
                    $query->whereRelation('order', 'amount', 'LIKE', '%' . $amount . '%');
                    $query->orWhereRelation('order', 'total_profit', 'LIKE', '%' . $amount . '%');
                });
            })
            ->when($filters->paymentDetail, function ($query) use ($filters) {
                $query->whereRelation('order.paymentDetail', 'detail', 'LIKE', '%' . $filters->paymentDetail . '%');
            })
            ->when($filters->externalID, function ($query) use ($filters) {
                $query->whereRelation('order', 'external_id', 'LIKE', '%' . $filters->externalID . '%');
            })
            ->with(['order.paymentDetail.user', 'order.paymentGateway'])
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);
    }
}
