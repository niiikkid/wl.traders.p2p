<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\PaymentDetail\VolumeStatisticsRequest;
use App\Http\Resources\PaymentDetailResource;
use App\Models\PaymentDetail;
use App\Services\PaymentDetail\PaymentDetailVolumeStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PaymentDetailVolumeStatisticsController extends Controller
{
    public function __construct(
        private readonly PaymentDetailVolumeStatisticsService $volumeStatisticsService,
    ) {}

    public function show(VolumeStatisticsRequest $request, PaymentDetail $paymentDetail): JsonResponse
    {
        Gate::authorize('access-to-payment-detail', $paymentDetail);

        $paymentDetail->load(['user', 'userDevice', 'paymentGateways', 'schedule.intervals']);
        $paymentDetail->loadCount(['orders as pending_orders_count' => function ($query) {
            $query->where('status', OrderStatus::PENDING);
        }]);

        return response()->json([
            ...$this->volumeStatisticsService->buildModalPayload($paymentDetail, $request->period()),
            'context_detail' => PaymentDetailResource::make($paymentDetail)->resolve(),
        ]);
    }
}
