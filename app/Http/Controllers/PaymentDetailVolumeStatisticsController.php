<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\PaymentDetail\VolumeStatisticsRequest;
use App\Http\Resources\PaymentDetailResource;
use App\Models\PaymentDetail;
use App\Services\PaymentDetail\PaymentDetailVolumeStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
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

        $period = $request->period();
        $cacheKey = sprintf(
            'payment_detail_volume_statistics:%d:%s:%s',
            $paymentDetail->id,
            $period,
            $paymentDetail->updated_at?->timestamp ?? 0,
        );

        return response()->json(Cache::remember(
            $cacheKey,
            now()->addMinute(),
            fn (): array => [
                ...$this->volumeStatisticsService->buildModalPayload($paymentDetail, $period),
                'context_detail' => PaymentDetailResource::make($paymentDetail)->resolve(),
            ],
        ));
    }
}
