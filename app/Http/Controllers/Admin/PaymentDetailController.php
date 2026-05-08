<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentDetailBankStatisticResource;
use App\Http\Resources\PaymentDetailResource;
use App\Models\PaymentGateway;
use Inertia\Inertia;

class PaymentDetailController extends Controller
{
    public function index()
    {
        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $fromArchive = request()->tab === 'archived';

        $paymentDetails = queries()->paymentDetail()->paginateForAdmin($filters, $fromArchive);

        $paymentDetails = PaymentDetailResource::collection($paymentDetails);

        return Inertia::render('PaymentDetail/Index', compact('paymentDetails', 'filters', 'filtersVariants'));
    }

    public function statistics()
    {
        $filters = [];
        $filtersVariants = [];

        $paymentDetailBankStats = PaymentGateway::query()
            ->whereHas('paymentDetails')
            ->withCount('paymentDetails')
            ->withSum([
                'orders as successful_orders_total_turnover_usdt' => fn ($query) => $query
                    ->where('status', OrderStatus::SUCCESS),
            ], 'total_profit')
            ->orderByDesc('payment_details_count')
            ->orderBy('name')
            ->paginate(request()->per_page ?? 10);

        $paymentDetailBankStats = PaymentDetailBankStatisticResource::collection($paymentDetailBankStats);

        return Inertia::render('PaymentDetail/Statistics', compact('paymentDetailBankStats', 'filters', 'filtersVariants'));
    }
}
