<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function index()
    {
        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $orders = queries()->order()->paginateForMerchant(request()->user(), $filters);
        $orders = OrderResource::collection($orders);

        return Inertia::render('Payment/Index', compact('orders', 'filters', 'filtersVariants'));
    }
}
