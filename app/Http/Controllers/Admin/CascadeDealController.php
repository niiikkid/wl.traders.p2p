<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TableCascadeDealResource;
use App\Models\CascadeDeal;
use Inertia\Inertia;

class CascadeDealController extends Controller
{
    public function index()
    {
        $filters = [];
        $filtersVariants = [];

        $cascadeDeals = CascadeDeal::query()
            ->with(['merchant', 'merchantClient', 'order', 'selectedProvider', 'selectedTransaction'])
            ->withCount(['transactions', 'providerLogs'])
            ->latest()
            ->paginate(request()->integer('per_page', 10))
            ->withQueryString();

        $cascadeDeals = TableCascadeDealResource::collection($cascadeDeals);

        return Inertia::render('Admin/CascadeDeals/Index', compact('cascadeDeals', 'filters', 'filtersVariants'));
    }
}
