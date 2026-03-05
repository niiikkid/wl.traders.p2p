<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Http\Resources\TableOrderResource;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index()
    {
        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $orders = queries()->order()->paginateForAdmin($filters);
        $orders = TableOrderResource::collection($orders);

        return Inertia::render('Support/Order/Index', compact('orders', 'filters', 'filtersVariants'));
    }
} 