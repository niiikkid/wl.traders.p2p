<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentDetailResource;
use App\Models\PaymentDetail;
use App\Services\PaymentDetail\PaymentDetailScheduleAvailabilityService;
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
        $scheduleAvailabilityService = app(PaymentDetailScheduleAvailabilityService::class);
        $scheduleServerClock = $scheduleAvailabilityService->serverClockPayload();
        $scheduleSummary = $scheduleAvailabilityService->buildPaymentDetailSummary(
            PaymentDetail::query()
                ->when(! $fromArchive, fn ($query) => $query->whereNull('archived_at'))
                ->when($fromArchive, fn ($query) => $query->whereNotNull('archived_at')),
        );

        return Inertia::render('PaymentDetail/Index', compact('paymentDetails', 'filters', 'filtersVariants', 'scheduleServerClock', 'scheduleSummary'));
    }
}
