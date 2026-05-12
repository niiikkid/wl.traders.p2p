<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentDetailBankStatisticResource;
use App\Http\Resources\PaymentDetailResource;
use App\Models\PaymentGateway;
use Carbon\Carbon;
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
        $period = request()->string('period')->toString() ?: 'all';
        $periodStartAt = $this->resolveStatisticsPeriodStartAt($period);

        $baseQuery = PaymentGateway::query()
            ->whereHas('paymentDetails', function ($query) use ($periodStartAt) {
                if ($periodStartAt) {
                    $query->where('payment_details.created_at', '>=', $periodStartAt);
                }
            });

        $totalPaymentDetailsCount = (clone $baseQuery)
            ->withCount([
                'paymentDetails as payment_details_count' => function ($query) use ($periodStartAt) {
                    if ($periodStartAt) {
                        $query->where('payment_details.created_at', '>=', $periodStartAt);
                    }
                },
            ])
            ->get()
            ->sum('payment_details_count');

        $totalSuccessfulOrdersTurnoverUsdt = (clone $baseQuery)
            ->withSum([
                'orders as successful_orders_total_turnover_usdt' => fn ($query) => $query
                    ->where('status', OrderStatus::SUCCESS)
                    ->when($periodStartAt, fn ($query) => $query->whereHas('paymentDetail', fn ($paymentDetailQuery) => $paymentDetailQuery
                        ->where('payment_details.created_at', '>=', $periodStartAt))),
            ], 'total_profit')
            ->get()
            ->sum(fn (PaymentGateway $paymentGateway) => (int) ($paymentGateway->successful_orders_total_turnover_usdt ?? 0));

        $paymentDetailBankStats = $baseQuery
            ->withCount([
                'paymentDetails as payment_details_count' => function ($query) use ($periodStartAt) {
                    if ($periodStartAt) {
                        $query->where('payment_details.created_at', '>=', $periodStartAt);
                    }
                },
            ])
            ->withSum([
                'orders as successful_orders_total_turnover_usdt' => fn ($query) => $query
                    ->where('status', OrderStatus::SUCCESS)
                    ->when($periodStartAt, fn ($query) => $query->whereHas('paymentDetail', fn ($paymentDetailQuery) => $paymentDetailQuery
                        ->where('payment_details.created_at', '>=', $periodStartAt))),
            ], 'total_profit')
            ->orderByDesc('payment_details_count')
            ->orderBy('name')
            ->paginate(request()->per_page ?? 10);

        $paymentDetailBankStats->setCollection(
            $paymentDetailBankStats->getCollection()->map(function (PaymentGateway $paymentGateway) use ($totalPaymentDetailsCount, $totalSuccessfulOrdersTurnoverUsdt) {
                $paymentDetailsCount = (int) ($paymentGateway->payment_details_count ?? 0);
                $successfulOrdersTurnoverUsdt = (int) ($paymentGateway->successful_orders_total_turnover_usdt ?? 0);

                $paymentGateway->setAttribute(
                    'payment_details_percent',
                    $totalPaymentDetailsCount > 0 ? round(($paymentDetailsCount / $totalPaymentDetailsCount) * 100, 2) : 0.0
                );
                $paymentGateway->setAttribute(
                    'successful_orders_total_turnover_percent',
                    $totalSuccessfulOrdersTurnoverUsdt > 0 ? round(($successfulOrdersTurnoverUsdt / $totalSuccessfulOrdersTurnoverUsdt) * 100, 2) : 0.0
                );

                return $paymentGateway;
            })
        );

        $paymentDetailBankStats = PaymentDetailBankStatisticResource::collection($paymentDetailBankStats);

        $periodOptions = [
            ['value' => 'all', 'label' => 'За всё время'],
            ['value' => '7d', 'label' => 'За 7 дней'],
            ['value' => '14d', 'label' => 'За 2 недели'],
            ['value' => '30d', 'label' => 'За месяц'],
        ];

        return Inertia::render('PaymentDetail/Statistics', compact('paymentDetailBankStats', 'filters', 'filtersVariants', 'period', 'periodOptions'));
    }

    private function resolveStatisticsPeriodStartAt(string $period): ?Carbon
    {
        return match ($period) {
            '7d' => now()->subDays(7),
            '14d' => now()->subDays(14),
            '30d' => now()->subDays(30),
            default => null,
        };
    }
}
