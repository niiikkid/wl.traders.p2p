<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PaymentDetail\VolumeStatisticsRequest;
use App\Models\User;
use App\Services\PaymentDetail\PaymentDetailVolumeStatisticsService;
use Inertia\Inertia;
use Inertia\Response;

class PaymentDetailVolumeStatisticsController extends Controller
{
    public function __construct(
        private readonly PaymentDetailVolumeStatisticsService $volumeStatisticsService,
    ) {}

    public function index(VolumeStatisticsRequest $request): Response
    {
        $isAdmin = $request->routeIs('admin.payment-details.volume-statistics');
        $period = $request->period();
        [$periodStartAt, $periodEndAt] = $this->volumeStatisticsService->resolvePeriodBounds(
            $period,
            $request->dateFrom(),
            $request->dateTo(),
        );

        $userId = $isAdmin ? $request->traderId() : (int) auth()->id();
        $barsLimit = $this->volumeStatisticsService->resolveBarsLimit($request->barsLimit());
        $includeArchived = $request->includeArchived();
        $paymentGatewayId = $request->paymentGatewayId();
        $bankOptions = $this->volumeStatisticsService->bankOptions($userId, $includeArchived);

        if (
            $paymentGatewayId !== null
            && ! collect($bankOptions)->contains(fn (array $option): bool => $option['value'] === $paymentGatewayId)
        ) {
            $paymentGatewayId = null;
        }

        $chartResult = $this->volumeStatisticsService->buildChart(
            $userId,
            $periodStartAt,
            $periodEndAt,
            $barsLimit,
            $request->page(),
            $includeArchived,
            $paymentGatewayId,
            $request->volumeFrom(),
            $request->volumeTo(),
        );

        $chartPayload = $this->volumeStatisticsService->formatChartPayload($chartResult['items']);
        $dealAmountDistribution = $this->volumeStatisticsService->buildDealAmountDistribution(
            $userId,
            $periodStartAt,
            $periodEndAt,
            $includeArchived,
            $paymentGatewayId,
            $request->volumeFrom(),
            $request->volumeTo(),
            $chartPayload['ids'],
        );
        $selectedTrader = $isAdmin && $userId !== null
            ? User::query()->select(['id', 'email'])->find($userId)
            : null;

        return Inertia::render('PaymentDetail/VolumeStatistics', [
            'chart' => [
                'labels' => $chartPayload['labels'],
                'series' => [
                    [
                        'name' => 'Объём USDT',
                        'data' => $chartPayload['data'],
                    ],
                ],
                'colors' => $chartPayload['colors'],
                'volumes' => $chartPayload['volumes'],
                'ids' => $chartPayload['ids'],
            ],
            'dealAmountDistribution' => $dealAmountDistribution['aggregate'],
            'dealAmountDistributionByDetail' => $dealAmountDistribution['by_payment_detail'],
            'meta' => [
                ...$chartResult['meta'],
                'scope_all_traders' => $isAdmin && $userId === null,
            ],
            'filters' => [
                'period' => $period,
                'date_from' => $request->dateFrom(),
                'date_to' => $request->dateTo(),
                'trader_id' => $isAdmin ? $userId : null,
                'bars_limit' => $chartResult['meta']['bars_limit'],
                'include_archived' => $includeArchived,
                'payment_gateway_id' => $paymentGatewayId,
                'volume_from' => $chartResult['meta']['volume_from'],
                'volume_to' => $chartResult['meta']['volume_to'],
                'page' => $chartResult['meta']['current_page'],
                'view_mode' => $request->viewMode(),
            ],
            'volumePresets' => $chartResult['volume_presets'],
            'bankOptions' => $bankOptions,
            'selectedBank' => $paymentGatewayId === null
                ? null
                : collect($bankOptions)->firstWhere('value', $paymentGatewayId),
            'barsLimitPresets' => [
                ['value' => '25', 'label' => '25'],
                ['value' => '50', 'label' => '50'],
                ['value' => '75', 'label' => '75'],
                ['value' => '100', 'label' => '100'],
                ['value' => '200', 'label' => '200'],
            ],
            'defaultBarsLimit' => (string) PaymentDetailVolumeStatisticsService::DEFAULT_BARS_LIMIT,
            'selectedTrader' => $selectedTrader === null ? null : [
                'id' => $selectedTrader->id,
                'label' => $selectedTrader->email,
            ],
            'periodOptions' => [
                ['value' => '1d', 'label' => 'За день'],
                ['value' => '7d', 'label' => 'За 7 дней'],
                ['value' => '14d', 'label' => 'За 2 недели'],
                ['value' => '30d', 'label' => 'За месяц'],
                ['value' => 'all', 'label' => 'За всё время'],
            ],
            'isAdmin' => $isAdmin,
            'traderSearchRoute' => $isAdmin ? route('admin.main.filter-options', ['type' => 'trader']) : null,
            'backRoute' => $isAdmin ? 'admin.payment-details.index' : 'payment-details.index',
        ]);
    }
}
