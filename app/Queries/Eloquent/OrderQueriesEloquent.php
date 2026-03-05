<?php

namespace App\Queries\Eloquent;

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Models\UserDevice;
use App\ObjectValues\TableFilters\TableFiltersValue;
use App\Queries\Interfaces\OrderQueries;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class OrderQueriesEloquent implements OrderQueries
{
    public function findPending(Money $amount, User $user, PaymentGateway $paymentGateway, UserDevice $device): ?Order
    {
        return Order::where('amount', $amount->toUnits())
            ->whereDoesntHave('dispute')
            ->where('status', OrderStatus::PENDING)
            ->where('currency', $amount->getCurrency()->getCode())
            ->where('trader_id', $user->id)
            ->whereRelation('paymentDetail', 'user_device_id', $device->id)
            ->where('payment_gateway_id', $paymentGateway->id)
            ->first();
    }

    public function paginateForAdmin(TableFiltersValue $filters): LengthAwarePaginator
    {
        return Order::query()
            ->whereNotNull('payment_detail_id')
            ->with([
                'trader:id,email,name',
                'paymentGateway:id,logo,name',
                'paymentDetail:id,detail,detail_type,name,user_device_id,user_id',
                'paymentDetail.userDevice:id,name',
                'paymentDetail.user:id,name,email',
                'dispute' => function ($query) {
                    $query->where('status', DisputeStatus::PENDING->value)
                        ->select(['id', 'order_id', 'status', 'reason', 'receipt', 'created_at']);
                },
            ])
            ->select(['id', 'uuid', 'amount', 'currency', 'total_profit', 'status', 'created_at', 'payment_gateway_id', 'payment_detail_id', 'trader_id'])
            ->withExists('dispute')
            ->when(! empty($filters->orderStatuses), function ($query) use ($filters) {
                $query->whereIn('status', $filters->orderStatuses);
            })
            ->when($filters->startDate, function ($query) use ($filters) {
                $query->whereDate('created_at', '>=', $filters->startDate);
            })
            ->when($filters->endDate, function ($query) use ($filters) {
                $query->whereDate('created_at', '<=', $filters->endDate);
            })
            ->when($filters->externalID, function ($query) use ($filters) {
                $query->where('external_id', 'LIKE', '%' . $filters->externalID . '%');
            })
            ->when($filters->uuid, function ($query) use ($filters) {
                $query->where('uuid', 'LIKE', '%' . $filters->uuid . '%');
            })
            ->when($filters->amount, function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $amount = Money::fromPrecision($filters->amount, Currency::USDT())->toUnits();
                    $query->where('amount', 'LIKE', '%' . $amount . '%');
                    $query->orWhere('total_profit', 'LIKE', '%' . $amount . '%');
                });
            })
            ->when($filters->paymentDetail, function ($query) use ($filters) {
                $query->whereRelation('paymentDetail', 'detail', 'LIKE', '%' . $filters->paymentDetail . '%');
            })
            ->when($filters->detailTypes && count($filters->detailTypes) > 0, function ($query) use ($filters) {
                $query->whereRelation('paymentDetail', function ($subQuery) use ($filters) {
                    $subQuery->whereIn('detail_type', $filters->detailTypes);
                });
            })
            ->when($filters->paymentGateway, function ($query) use ($filters) {
                $query->whereRelation('paymentGateway', function ($subQuery) use ($filters) {
                    $subQuery->where('name', 'LIKE', '%' . $filters->paymentGateway . '%')
                        ->orWhere('code', 'LIKE', '%' . $filters->paymentGateway . '%');
                });
            })
            ->when($filters->user, function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->whereRelation('paymentDetail.user', 'name', 'LIKE', '%' . $filters->user . '%');
                    $query->orWhereRelation('paymentDetail.user', 'email', 'LIKE', '%' . $filters->user . '%');
                });
            })
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);
    }

    public function paginateForUser(User $user, TableFiltersValue $filters): LengthAwarePaginator
    {
        return Order::query()
            ->whereRelation('paymentDetail', 'user_id', $user->id)
            ->with([
                'trader:id,email',
                'paymentGateway:id,logo,name',
                'paymentDetail:id,detail,detail_type,name,user_device_id,user_id',
                'paymentDetail.userDevice:id,name',
                'paymentDetail.user:id,name,email',
                'dispute' => function ($query) {
                    $query->where('status', DisputeStatus::PENDING->value)
                        ->select(['id', 'order_id', 'status', 'reason', 'receipt', 'created_at']);
                },
            ])
            ->whereNotNull('payment_detail_id')
            ->when(! empty($filters->orderStatuses), function ($query) use ($filters) {
                $query->whereIn('status', $filters->orderStatuses);
            })
            ->when($filters->startDate, function ($query) use ($filters) {
                $query->whereDate('created_at', '>=', $filters->startDate);
            })
            ->when($filters->endDate, function ($query) use ($filters) {
                $query->whereDate('created_at', '<=', $filters->endDate);
            })
            ->when($filters->uuid, function ($query) use ($filters) {
                $query->where('uuid', 'LIKE', '%' . $filters->uuid . '%');
            })
            ->when($filters->amount, function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $amount = Money::fromPrecision($filters->amount, Currency::USDT())->toUnits();
                    $query->where('amount', 'LIKE', $amount);
                    $query->orWhere('total_profit', 'LIKE', $amount);
                });
            })
            ->when($filters->paymentDetail, function ($query) use ($filters) {
                $query->whereRelation('paymentDetail', 'detail', 'LIKE', '%' . $filters->paymentDetail . '%');
            })
            ->when($filters->detailTypes && count($filters->detailTypes) > 0, function ($query) use ($filters) {
                $query->whereRelation('paymentDetail', function ($subQuery) use ($filters) {
                    $subQuery->whereIn('detail_type', $filters->detailTypes);
                });
            })
            ->when($filters->paymentGateway, function ($query) use ($filters) {
                $query->whereRelation('paymentGateway', function ($subQuery) use ($filters) {
                    $subQuery->where('name', 'LIKE', '%' . $filters->paymentGateway . '%')
                        ->orWhere('code', 'LIKE', '%' . $filters->paymentGateway . '%');
                });
            })
            ->select(['id', 'uuid', 'amount', 'currency', 'total_profit', 'status', 'created_at', 'payment_gateway_id', 'payment_detail_id', 'trader_id'])
            ->withExists('dispute')
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);
    }

    public function paginateForMerchant(User $user, TableFiltersValue $filters): LengthAwarePaginator
    {
        return Order::query()
            ->withoutGlobalScopes()
            ->whereNotNull('payment_detail_id')
            ->with(['merchant'])
            ->whereRelation('merchant', 'user_id', $user->id)
            ->when(! empty($filters->merchantIds), function ($query) use ($filters) {
                $query->whereIn('merchant_id', $filters->merchantIds);
            })
            ->when(! empty($filters->orderStatuses), function ($query) use ($filters) {
                $query->whereIn('status', $filters->orderStatuses);
            })
            ->when($filters->externalID, function ($query) use ($filters) {
                $query->where('external_id', 'LIKE', '%' . $filters->externalID . '%');
            })
            ->when($filters->uuid, function ($query) use ($filters) {
                $query->where('uuid', 'LIKE', '%' . $filters->uuid . '%');
            })
            ->when($filters->amount, function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $amount = Money::fromPrecision($filters->amount, Currency::USDT())->toUnits();
                    $query->where('amount', 'LIKE', $amount);
                    $query->orWhere('total_profit', 'LIKE', $amount);
                });
            })
            ->when($filters->detailTypes && count($filters->detailTypes) > 0, function ($query) use ($filters) {
                $query->whereRelation('paymentDetail', function ($subQuery) use ($filters) {
                    $subQuery->whereIn('detail_type', $filters->detailTypes);
                });
            })
            ->when($filters->paymentGateway, function ($query) use ($filters) {
                $query->whereRelation('paymentGateway', function ($subQuery) use ($filters) {
                    $subQuery->where('name', 'LIKE', '%' . $filters->paymentGateway . '%')
                        ->orWhere('code', 'LIKE', '%' . $filters->paymentGateway . '%');
                });
            })
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);
    }


    /**
     * @return Collection<int, Dispute>
     */
    public function getForAdminApiDisputeCreate(): Collection
    {
        return Order::query()
            ->where('status', OrderStatus::FAIL)
            ->whereDoesntHave('dispute')
            ->whereDate('created_at', '>=', now()->subDay())
            ->orderByDesc('id')
            ->get(['id', 'amount', 'currency']);
    }
}
