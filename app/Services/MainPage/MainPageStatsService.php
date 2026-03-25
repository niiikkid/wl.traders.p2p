<?php

namespace App\Services\MainPage;

use App\Contracts\MainPageStatsServiceContract;
use App\Enums\BalanceType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Carbon\Carbon;
use Throwable;
use Illuminate\Database\Eloquent\Builder;

class MainPageStatsService implements MainPageStatsServiceContract
{
    public function buildMerchantStats(User $user): array
    {
        $query = Order::query()
            ->whereRelation('merchant', 'user_id', $user->id)
            ->where('status', OrderStatus::SUCCESS);

        $totalProfit = Money::fromUnits($query->clone()->sum('merchant_profit'), Currency::USDT());

        $totalWithdrawalAmount = Invoice::query()
            ->whereRelation('wallet', 'user_id', $user->id)
            ->where('type', InvoiceType::WITHDRAWAL)
            ->where('balance_type', BalanceType::MERCHANT)
            ->where('status', InvoiceStatus::SUCCESS)
            ->sum('amount');
        $totalWithdrawalAmount = Money::fromUnits($totalWithdrawalAmount, Currency::USDT());

        $balance = $user->wallet
            ? services()->wallet()->getTotalAvailableBalance($user->wallet, BalanceType::MERCHANT)
            : Money::fromUnits(0, Currency::USDT());

        $successOrderCount = $query->clone()->count();

        $startDate = now()->subDays(29);
        $endDate = now();

        $earningsByDay = Order::where('status', OrderStatus::SUCCESS)
            ->whereRelation('merchant', 'user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, SUM(merchant_profit) as total_earnings')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $labels = [];
        $data = [];

        for ($i = 0; $i < 30; $i++) {
            $date = $startDate->copy()->addDays($i);
            $labels[] = $date->day;
            $data[] = Money::fromUnits(
                $earningsByDay->firstWhere('date', $date->toDateString())->total_earnings ?? 0,
                Currency::USDT()
            )->toInt();
        }

        return [
            'statistics' => [
                'totalProfit' => $totalProfit->toBeauty(),
                'totalWithdrawalAmount' => $totalWithdrawalAmount->toBeauty(),
                'balance' => $balance->toBeauty(),
                'successOrderCount' => $successOrderCount,
            ],
            'chart' => [
                'labels' => $labels,
                'data' => $data,
            ]
        ];
    }

    public function buildTraderStats(User $user): array
    {
        $query = Order::query()
            ->whereRelation('paymentDetail', 'user_id', $user->id)
            ->where('status', OrderStatus::SUCCESS);

        $totalTurnover = Money::fromUnits($query->clone()->sum('total_profit'), Currency::USDT());
        $totalProfit = Money::fromUnits($query->clone()->sum('trader_profit'), Currency::USDT());

        $balance = $user->wallet
            ? services()->wallet()->getTotalAvailableBalance($user->wallet, BalanceType::TRUST)
            : Money::fromUnits(0, Currency::USDT());

        $successOrderCount = $query->clone()->count();

        $startDate = now()->subDays(29);
        $endDate = now();

        $earningsByDay = Order::where('status', OrderStatus::SUCCESS)
            ->whereRelation('paymentDetail', 'user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, SUM(trader_profit) as total_earnings')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $labels = [];
        $data = [];

        for ($i = 0; $i < 30; $i++) {
            $date = $startDate->copy()->addDays($i);
            $labels[] = $date->day;
            $data[] = Money::fromUnits(
                $earningsByDay->firstWhere('date', $date->toDateString())->total_earnings ?? 0,
                Currency::USDT()
            )->toInt();
        }

        return [
            'statistics' => [
                'totalTurnover' => $totalTurnover->toBeauty(),
                'totalProfit' => $totalProfit->toBeauty(),
                'balance' => $balance->toBeauty(),
                'successOrderCount' => $successOrderCount,
            ],
            'chart' => [
                'labels' => $labels,
                'data' => $data,
            ]
        ];
    }

    public function buildLeaderStats(User $user): array
    {
        $referralsIds = User::query()
            ->where('team_leader_id', $user->id)
            ->pluck('id');
        $referralsCount = $referralsIds->count();

        $query = Order::query()
            ->where('team_leader_id', $user->id)
            ->where('status', OrderStatus::SUCCESS);

        $totalProfit = Money::fromUnits($query->clone()->sum('team_leader_profit'), Currency::USDT());
        $successOrderCount = $query->clone()->count();
        $referralRate = $user->referral_commission_percentage;

        $balance = $user->wallet
            ? services()->wallet()->getTotalAvailableBalance($user->wallet, BalanceType::TEAMLEADER)
            : Money::fromUnits(0, Currency::USDT());

        $startDate = now()->subDays(29);
        $endDate = now();

        $earningsByDay = Order::where('status', OrderStatus::SUCCESS)
            ->where('team_leader_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, SUM(team_leader_profit) as total_earnings')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $labels = [];
        $data = [];

        for ($i = 0; $i < 30; $i++) {
            $date = $startDate->copy()->addDays($i);
            $labels[] = $date->day;
            $data[] = Money::fromUnits(
                $earningsByDay->firstWhere('date', $date->toDateString())->total_earnings ?? 0,
                Currency::USDT()
            )->toInt();
        }

        return [
            'statistics' => [
                'totalProfit' => $totalProfit->toBeauty(),
                'successOrderCount' => $successOrderCount,
                'referralsCount' => $referralsCount,
                'referralRate' => $referralRate,
                'balance' => $balance->toBeauty(),
            ],
            'chart' => [
                'labels' => $labels,
                'data' => $data,
            ]
        ];
    }

    public function buildAdminStats(
        User $user,
        ?int $merchantId = null,
        string $periodPreset = 'all',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        array $filters = [],
    ): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        $resolvedPeriod = $this->resolvePeriodRange($periodPreset, $dateFrom, $dateTo);
        $startDate = $resolvedPeriod['startDate'];
        $endDate = $resolvedPeriod['endDate'];

        if ($resolvedPeriod['preset'] === 'all') {
            $allBoundsQuery = Order::query();
            if ($merchantId) {
                $allBoundsQuery->where('merchant_id', $merchantId);
            }
            $this->applyOrderFilters($allBoundsQuery, $normalizedFilters);

            $minCreatedAt = $allBoundsQuery->min('created_at');
            if ($minCreatedAt) {
                $startDate = Carbon::parse($minCreatedAt)->startOfDay();
            } else {
                $startDate = now()->startOfDay();
            }

            $endDate = now()->endOfDay();
            $resolvedPeriod['dateFrom'] = $startDate->toDateString();
            $resolvedPeriod['dateTo'] = $endDate->toDateString();
        }

        $isHourly = $startDate->isSameDay($endDate);
        $bucketSql = $isHourly
            ? "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')"
            : "DATE(created_at)";

        $query = Order::query()
            ->where('status', OrderStatus::SUCCESS)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }

        $this->applyOrderFilters($query, $normalizedFilters);

        $totalTurnover = Money::fromUnits($query->clone()->sum('total_profit'), Currency::USDT());
        $totalProfit = Money::fromUnits($query->clone()->sum('service_profit'), Currency::USDT());

        $successOrderQuery = Order::query()
            ->where('status', OrderStatus::SUCCESS)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($merchantId) {
            $successOrderQuery->where('merchant_id', $merchantId);
        }

        $this->applyOrderFilters($successOrderQuery, $normalizedFilters);

        $successOrderCount = $successOrderQuery->count();

        $failedOrderQuery = Order::query()
            ->where('status', OrderStatus::FAIL)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($merchantId) {
            $failedOrderQuery->where('merchant_id', $merchantId);
        }

        $this->applyOrderFilters($failedOrderQuery, $normalizedFilters);

        $failedOrderCount = $failedOrderQuery->count();

        $totalOrderCount = $successOrderCount + $failedOrderCount;
        $conversionRate = $totalOrderCount > 0
            ? round(($successOrderCount / $totalOrderCount) * 100, 2)
            : 0;

        $earningsByDayQuery = Order::where('status', OrderStatus::SUCCESS)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($merchantId) {
            $earningsByDayQuery->where('merchant_id', $merchantId);
        }

        $this->applyOrderFilters($earningsByDayQuery, $normalizedFilters);

        $earningsByDay = $earningsByDayQuery
            ->selectRaw("{$bucketSql} as bucket_key, SUM(service_profit) as total_earnings")
            ->groupBy('bucket_key')
            ->pluck('total_earnings', 'bucket_key');

        $turnoverByDayQuery = Order::where('status', OrderStatus::SUCCESS)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($merchantId) {
            $turnoverByDayQuery->where('merchant_id', $merchantId);
        }

        $this->applyOrderFilters($turnoverByDayQuery, $normalizedFilters);

        $turnoverByDay = $turnoverByDayQuery
            ->selectRaw("{$bucketSql} as bucket_key, SUM(total_profit) as total_turnover")
            ->groupBy('bucket_key')
            ->pluck('total_turnover', 'bucket_key');

        $labels = [];
        $incomeData = [];
        $turnoverData = [];
        $conversionData = [];
        $ordersData = [];

        $successOrdersByDayQuery = Order::where('status', OrderStatus::SUCCESS)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($merchantId) {
            $successOrdersByDayQuery->where('merchant_id', $merchantId);
        }

        $this->applyOrderFilters($successOrdersByDayQuery, $normalizedFilters);

        $successOrdersByDay = $successOrdersByDayQuery
            ->selectRaw("{$bucketSql} as bucket_key, COUNT(*) as count")
            ->groupBy('bucket_key')
            ->pluck('count', 'bucket_key');

        $failedOrdersByDayQuery = Order::where('status', OrderStatus::FAIL)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($merchantId) {
            $failedOrdersByDayQuery->where('merchant_id', $merchantId);
        }

        $this->applyOrderFilters($failedOrdersByDayQuery, $normalizedFilters);

        $failedOrdersByDay = $failedOrdersByDayQuery
            ->selectRaw("{$bucketSql} as bucket_key, COUNT(*) as count")
            ->groupBy('bucket_key')
            ->pluck('count', 'bucket_key');

        $buckets = [];
        $currentBucketDate = $startDate->copy();
        while ($currentBucketDate->lte($endDate)) {
            $bucketKey = $isHourly
                ? $currentBucketDate->format('Y-m-d H:00:00')
                : $currentBucketDate->toDateString();

            $label = $isHourly
                ? $currentBucketDate->format('H:i')
                : $currentBucketDate->format('d.m');
            $income = Money::fromUnits(
                (int) ($earningsByDay[$bucketKey] ?? 0),
                Currency::USDT()
            )->toInt();
            $turnover = Money::fromUnits(
                (int) ($turnoverByDay[$bucketKey] ?? 0),
                Currency::USDT()
            )->toInt();
            $successCount = (int) ($successOrdersByDay[$bucketKey] ?? 0);
            $failedCount = (int) ($failedOrdersByDay[$bucketKey] ?? 0);
            $buckets[] = [
                'label' => $label,
                'income' => $income,
                'turnover' => $turnover,
                'successCount' => $successCount,
                'failedCount' => $failedCount,
            ];

            if ($isHourly) {
                $currentBucketDate->addHour();
            } else {
                $currentBucketDate->addDay();
            }
        }

        if (in_array($resolvedPeriod['preset'], ['custom', 'all'], true) && count($buckets) > 30) {
            $chunkSize = (int) ceil(count($buckets) / 30);
            $groupedBuckets = [];

            for ($i = 0; $i < count($buckets); $i += $chunkSize) {
                $chunk = array_slice($buckets, $i, $chunkSize);
                $firstLabel = $chunk[0]['label'];
                $lastLabel = $chunk[count($chunk) - 1]['label'];

                $groupedBuckets[] = [
                    'label' => $firstLabel === $lastLabel ? $firstLabel : "{$firstLabel}-{$lastLabel}",
                    'income' => array_sum(array_column($chunk, 'income')),
                    'turnover' => array_sum(array_column($chunk, 'turnover')),
                    'successCount' => array_sum(array_column($chunk, 'successCount')),
                    'failedCount' => array_sum(array_column($chunk, 'failedCount')),
                ];
            }

            $buckets = $groupedBuckets;
        }

        foreach ($buckets as $bucket) {
            $totalCount = $bucket['successCount'] + $bucket['failedCount'];

            $labels[] = $bucket['label'];
            $incomeData[] = $bucket['income'];
            $turnoverData[] = $bucket['turnover'];
            $ordersData[] = $totalCount;
            $conversionData[] = $totalCount > 0
                ? round(($bucket['successCount'] / $totalCount) * 100, 2)
                : 0;
        }

        $pendingOrdersQuery = Order::query()
            ->where('status', OrderStatus::PENDING)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($merchantId) {
            $pendingOrdersQuery->where('merchant_id', $merchantId);
        }

        $this->applyOrderFilters($pendingOrdersQuery, $normalizedFilters);

        $pendingOrderCount = $pendingOrdersQuery->count();

        return [
            'statistics' => [
                'totalTurnover' => $totalTurnover->toBeauty(),
                'totalProfit' => $totalProfit->toBeauty(),
                'totalOrderCount' => $totalOrderCount,
                'successOrderCount' => $successOrderCount,
                'failedOrderCount' => $failedOrderCount,
                'conversionRate' => $conversionRate . '%',
                'pendingOrderCount' => $pendingOrderCount,
            ],
            'chart' => [
                'labels' => $labels,
                'data' => $incomeData,
            ],
            'conversionChart' => [
                'labels' => $labels,
                'data' => $conversionData,
            ],
            'turnoverChart' => [
                'labels' => $labels,
                'data' => $turnoverData,
            ],
            'ordersChart' => [
                'labels' => $labels,
                'data' => $ordersData,
            ],
            'selectedPeriodPreset' => $resolvedPeriod['preset'],
            'selectedDateFrom' => $resolvedPeriod['dateFrom'],
            'selectedDateTo' => $resolvedPeriod['dateTo'],
            'selectedFilters' => $normalizedFilters,
        ];
    }

    private function normalizeFilters(array $filters): array
    {
        return [
            'traderIds' => $this->normalizeIdArray($filters['traderIds'] ?? []),
            'paymentMethodIds' => $this->normalizeIdArray($filters['paymentMethodIds'] ?? []),
            'paymentDetailIds' => $this->normalizeIdArray($filters['paymentDetailIds'] ?? []),
            'merchantIds' => $this->normalizeIdArray($filters['merchantIds'] ?? []),
        ];
    }

    private function normalizeIdArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();
    }

    private function applyOrderFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['traderIds'])) {
            $query->whereIn('trader_id', $filters['traderIds']);
        }

        if (!empty($filters['paymentMethodIds'])) {
            $query->whereIn('payment_gateway_id', $filters['paymentMethodIds']);
        }

        if (!empty($filters['paymentDetailIds'])) {
            $query->whereIn('payment_detail_id', $filters['paymentDetailIds']);
        }

        if (!empty($filters['merchantIds'])) {
            $query->whereIn('merchant_id', $filters['merchantIds']);
        }
    }

    private function resolvePeriodRange(
        string $periodPreset,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        $normalizedPreset = in_array($periodPreset, ['today', 'week', 'month', 'custom', 'all'], true)
            ? $periodPreset
            : 'month';

        $now = now();
        $startDate = $now->copy()->subDays(29)->startOfDay();
        $endDate = $now->copy()->endOfDay();

        if ($normalizedPreset === 'today') {
            $startDate = $now->copy()->startOfDay();
            $endDate = $now->copy()->endOfDay();
        } elseif ($normalizedPreset === 'week') {
            $startDate = $now->copy()->subDays(6)->startOfDay();
            $endDate = $now->copy()->endOfDay();
        } elseif ($normalizedPreset === 'custom') {
            $parsedStart = $this->parseDateValue($dateFrom);
            $parsedEnd = $this->parseDateValue($dateTo);

            if ($parsedStart && $parsedEnd) {
                if ($parsedStart->gt($parsedEnd)) {
                    [$parsedStart, $parsedEnd] = [$parsedEnd, $parsedStart];
                }

                $startDate = $parsedStart->startOfDay();
                $endDate = $parsedEnd->endOfDay();
            } else {
                $normalizedPreset = 'all';
            }
        } elseif ($normalizedPreset === 'all') {
            $startDate = now()->startOfDay();
            $endDate = now()->endOfDay();
        }

        $isHourly = $startDate->isSameDay($endDate);

        return [
            'preset' => $normalizedPreset,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'isHourly' => $isHourly,
            'dateFrom' => $startDate->toDateString(),
            'dateTo' => $endDate->toDateString(),
        ];
    }

    private function parseDateValue(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }
    }
}

