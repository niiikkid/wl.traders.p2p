<?php

namespace App\Http\Controllers\MerchantSupport;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function index()
    {
        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $user = auth()->user();

        $orders = Order::query()
            ->withoutGlobalScopes()
            ->whereNotNull('payment_detail_id')
            ->whereIn('merchant_id', $user->merchants->pluck('id'))
            ->with(['merchant'])
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

        $orders = OrderResource::collection($orders);

        return Inertia::render('MerchantSupport/Payment/Index', compact('orders', 'filters', 'filtersVariants'));
    }
}
