<?php

namespace App\Http\Controllers;

use App\Http\Resources\MerchantCascadePaymentResource;
use App\Models\CascadeDeal;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function index()
    {
        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $orders = CascadeDeal::query()
            ->whereRelation('merchant', 'user_id', Auth::id())
            ->with('merchant:id,name,user_id')
            ->when(! empty($filters->merchantIds), function ($query) use ($filters) {
                $query->whereIn('merchant_id', $filters->merchantIds);
            })
            ->when(! empty($filters->orderStatuses), function ($query) use ($filters) {
                $query->whereIn('status', $filters->orderStatuses);
            })
            ->when($filters->externalID, function ($query) use ($filters) {
                $query->where('external_id', 'LIKE', '%'.$filters->externalID.'%');
            })
            ->when($filters->uuid, function ($query) use ($filters) {
                $query->where('uuid', 'LIKE', '%'.$filters->uuid.'%');
            })
            ->when($filters->amount, function ($query) use ($filters) {
                $amount = Money::fromPrecision($filters->amount, Currency::USDT())->toUnits();

                $query->where(function ($query) use ($amount) {
                    $query->where('amount', 'LIKE', $amount)
                        ->orWhere('usdt_amount', 'LIKE', $amount)
                        ->orWhere('credit', 'LIKE', $amount)
                        ->orWhere('fee', 'LIKE', $amount);
                });
            })
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);
        $orders = MerchantCascadePaymentResource::collection($orders);

        return Inertia::render('Payment/Index', compact('orders', 'filters', 'filtersVariants'));
    }
}
