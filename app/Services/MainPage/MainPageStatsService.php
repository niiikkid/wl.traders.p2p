<?php

namespace App\Services\MainPage;

use App\Contracts\MainPageStatsServiceContract;
use App\Enums\BalanceType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\PaymentDetail;
use App\Models\Payout\Payout;
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

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
            ],
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
            ],
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
            ],
        ];
    }

    public function buildAdminStats(
        User $user,
        ?int $merchantId = null,
        string $periodPreset = 'all',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        array $filters = [],
    ): array {
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
            : 'DATE(created_at)';

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
        $averageCheckData = [];

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

        $totalAmountByDayQuery = Order::query()
            ->whereIn('status', [OrderStatus::SUCCESS, OrderStatus::FAIL])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($merchantId) {
            $totalAmountByDayQuery->where('merchant_id', $merchantId);
        }

        $this->applyOrderFilters($totalAmountByDayQuery, $normalizedFilters);

        $totalAmountByDay = $totalAmountByDayQuery
            ->selectRaw("{$bucketSql} as bucket_key, SUM(total_profit) as total_amount")
            ->groupBy('bucket_key')
            ->pluck('total_amount', 'bucket_key');

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
            $totalAmount = Money::fromUnits(
                (int) ($totalAmountByDay[$bucketKey] ?? 0),
                Currency::USDT()
            )->toInt();
            $buckets[] = [
                'label' => $label,
                'income' => $income,
                'turnover' => $turnover,
                'successCount' => $successCount,
                'failedCount' => $failedCount,
                'totalAmount' => $totalAmount,
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
                    'totalAmount' => array_sum(array_column($chunk, 'totalAmount')),
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
            $averageCheckData[] = $totalCount > 0
                ? round($bucket['totalAmount'] / $totalCount, 2)
                : 0;
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
                'conversionRate' => $conversionRate.'%',
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
            'averageCheckChart' => [
                'labels' => $labels,
                'data' => $averageCheckData,
            ],
            'selectedPeriodPreset' => $resolvedPeriod['preset'],
            'selectedDateFrom' => $resolvedPeriod['dateFrom'],
            'selectedDateTo' => $resolvedPeriod['dateTo'],
            'selectedFilters' => $normalizedFilters,
        ];
    }

    public function buildAdminPayoutMainPageStats(
        User $user,
        ?int $merchantId = null,
        string $periodPreset = 'all',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        array $filters = [],
    ): array {
        $normalizedFilters = $this->normalizeAdminPayoutFilters($filters);
        $resolvedPeriod = $this->resolvePeriodRange($periodPreset, $dateFrom, $dateTo);
        $startDate = $resolvedPeriod['startDate'];
        $endDate = $resolvedPeriod['endDate'];

        $completedAtExpression = 'COALESCE(completed_at, updated_at)';
        $canceledAtExpression = 'COALESCE(canceled_at, updated_at)';

        if ($resolvedPeriod['preset'] === 'all') {
            $allBoundsQuery = Payout::query()
                ->where('status', PayoutStatus::COMPLETED->value);
            $this->applyAdminPayoutFilters($allBoundsQuery, $merchantId, $normalizedFilters);

            $minCompletedAt = $allBoundsQuery->min(DB::raw($completedAtExpression));
            if ($minCompletedAt) {
                $startDate = Carbon::parse($minCompletedAt)->startOfDay();
            } else {
                $startDate = now()->startOfDay();
            }

            $endDate = now()->endOfDay();
            $resolvedPeriod['dateFrom'] = $startDate->toDateString();
            $resolvedPeriod['dateTo'] = $endDate->toDateString();
        }

        $isHourly = $startDate->isSameDay($endDate);
        $bucketSql = $isHourly
            ? "DATE_FORMAT({$completedAtExpression}, '%Y-%m-%d %H:00:00')"
            : "DATE({$completedAtExpression})";

        $applyCompletedBetween = static function (Builder $query) use ($startDate, $endDate, $completedAtExpression): void {
            $query->whereRaw("{$completedAtExpression} between ? and ?", [$startDate, $endDate]);
        };

        $baseCompletedPayoutQuery = function () use ($merchantId, $normalizedFilters): Builder {
            $query = Payout::query()
                ->where('status', PayoutStatus::COMPLETED->value);
            $this->applyAdminPayoutFilters($query, $merchantId, $normalizedFilters);

            return $query;
        };

        $aggregatedQuery = $baseCompletedPayoutQuery();
        $applyCompletedBetween($aggregatedQuery);

        $totalTurnover = Money::fromUnits(
            (int) $aggregatedQuery->clone()->sum(DB::raw('CAST(IFNULL(usdt_body, 0) AS SIGNED)')),
            Currency::USDT(),
        );
        $totalProfit = Money::fromUnits(
            (int) $aggregatedQuery->clone()->sum(DB::raw('CAST(IFNULL(service_fee, 0) AS SIGNED)')),
            Currency::USDT(),
        );
        $successPayoutCount = (int) $aggregatedQuery->clone()->count();

        $failedPayoutQuery = Payout::query()
            ->where('status', PayoutStatus::CANCELED->value);
        $this->applyAdminPayoutFilters($failedPayoutQuery, $merchantId, $normalizedFilters);
        $failedPayoutQuery->whereRaw("{$canceledAtExpression} between ? and ?", [$startDate, $endDate]);
        $failedPayoutCount = (int) $failedPayoutQuery->count();

        $pendingPayoutQuery = Payout::query()
            ->whereIn('status', [
                PayoutStatus::OPEN->value,
                PayoutStatus::TAKEN->value,
                PayoutStatus::SENT->value,
            ]);
        $this->applyAdminPayoutFilters($pendingPayoutQuery, $merchantId, $normalizedFilters);
        $pendingPayoutQuery->whereBetween('created_at', [$startDate, $endDate]);
        $pendingPayoutCount = (int) $pendingPayoutQuery->count();

        $totalTerminalPayoutCount = $successPayoutCount + $failedPayoutCount;
        $conversionRate = $totalTerminalPayoutCount > 0
            ? round(($successPayoutCount / $totalTerminalPayoutCount) * 100, 2)
            : 0;

        $earningsByBucket = $baseCompletedPayoutQuery();
        $applyCompletedBetween($earningsByBucket);
        $earningsByBucket = $earningsByBucket
            ->selectRaw("{$bucketSql} as bucket_key, SUM(CAST(IFNULL(service_fee, 0) AS SIGNED)) as total_earnings")
            ->groupBy(DB::raw($bucketSql))
            ->pluck('total_earnings', 'bucket_key');

        $turnoverByBucket = $baseCompletedPayoutQuery();
        $applyCompletedBetween($turnoverByBucket);
        $turnoverByBucket = $turnoverByBucket
            ->selectRaw("{$bucketSql} as bucket_key, SUM(CAST(IFNULL(usdt_body, 0) AS SIGNED)) as total_turnover")
            ->groupBy(DB::raw($bucketSql))
            ->pluck('total_turnover', 'bucket_key');

        $countByBucket = $baseCompletedPayoutQuery();
        $applyCompletedBetween($countByBucket);
        $countByBucket = $countByBucket
            ->selectRaw("{$bucketSql} as bucket_key, COUNT(*) as cnt")
            ->groupBy(DB::raw($bucketSql))
            ->pluck('cnt', 'bucket_key');

        $canceledBucketSql = $isHourly
            ? "DATE_FORMAT({$canceledAtExpression}, '%Y-%m-%d %H:00:00')"
            : "DATE({$canceledAtExpression})";

        $canceledByBucketQuery = Payout::query()
            ->where('status', PayoutStatus::CANCELED->value);
        $this->applyAdminPayoutFilters($canceledByBucketQuery, $merchantId, $normalizedFilters);
        $canceledByBucketQuery->whereRaw("{$canceledAtExpression} between ? and ?", [$startDate, $endDate]);
        $canceledByBucket = $canceledByBucketQuery
            ->selectRaw("{$canceledBucketSql} as bucket_key, COUNT(*) as cnt")
            ->groupBy(DB::raw($canceledBucketSql))
            ->pluck('cnt', 'bucket_key');

        $labels = [];
        $incomeData = [];
        $turnoverData = [];
        $conversionData = [];
        $ordersData = [];
        $averageCheckData = [];

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
                (int) ($earningsByBucket[$bucketKey] ?? 0),
                Currency::USDT()
            )->toInt();
            $turnover = Money::fromUnits(
                (int) ($turnoverByBucket[$bucketKey] ?? 0),
                Currency::USDT()
            )->toInt();
            $payoutCount = (int) ($countByBucket[$bucketKey] ?? 0);
            $canceledCount = (int) ($canceledByBucket[$bucketKey] ?? 0);
            $terminalTotal = $payoutCount + $canceledCount;
            $conversionPercent = $terminalTotal > 0
                ? round(($payoutCount / $terminalTotal) * 100, 2)
                : 0;

            $buckets[] = [
                'label' => $label,
                'income' => $income,
                'turnover' => $turnover,
                'payoutCount' => $payoutCount,
                'canceledCount' => $canceledCount,
                'conversionPercent' => $conversionPercent,
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

                $sumCompleted = array_sum(array_column($chunk, 'payoutCount'));
                $sumCanceled = array_sum(array_column($chunk, 'canceledCount'));
                $sumTerminal = $sumCompleted + $sumCanceled;
                $chunkConversion = $sumTerminal > 0
                    ? round(($sumCompleted / $sumTerminal) * 100, 2)
                    : 0;

                $groupedBuckets[] = [
                    'label' => $firstLabel === $lastLabel ? $firstLabel : "{$firstLabel}-{$lastLabel}",
                    'income' => array_sum(array_column($chunk, 'income')),
                    'turnover' => array_sum(array_column($chunk, 'turnover')),
                    'payoutCount' => $sumCompleted,
                    'canceledCount' => $sumCanceled,
                    'conversionPercent' => $chunkConversion,
                ];
            }

            $buckets = $groupedBuckets;
        }

        foreach ($buckets as $bucket) {
            $labels[] = $bucket['label'];
            $incomeData[] = $bucket['income'];
            $turnoverData[] = $bucket['turnover'];
            $conversionData[] = $bucket['conversionPercent'];
            $ordersData[] = $bucket['payoutCount'];
            $averageCheckData[] = $bucket['payoutCount'] > 0
                ? round($bucket['turnover'] / $bucket['payoutCount'], 2)
                : 0;
        }

        $selectedFiltersForResponse = $this->normalizeFilters([
            'traderIds' => $normalizedFilters['traderIds'],
            'merchantIds' => $normalizedFilters['merchantIds'],
            'paymentMethodIds' => [],
            'paymentDetailIds' => [],
        ]);

        return [
            'statistics' => [
                'totalTurnover' => $totalTurnover->toBeauty(),
                'totalProfit' => $totalProfit->toBeauty(),
                'totalOrderCount' => $totalTerminalPayoutCount,
                'successOrderCount' => $successPayoutCount,
                'failedOrderCount' => $failedPayoutCount,
                'pendingOrderCount' => $pendingPayoutCount,
                'conversionRate' => $conversionRate.'%',
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
            'averageCheckChart' => [
                'labels' => $labels,
                'data' => $averageCheckData,
            ],
            'selectedPeriodPreset' => $resolvedPeriod['preset'],
            'selectedDateFrom' => $resolvedPeriod['dateFrom'],
            'selectedDateTo' => $resolvedPeriod['dateTo'],
            'selectedFilters' => $selectedFiltersForResponse,
        ];
    }

    public function buildTraderMainPageStats(
        User $user,
        string $periodPreset = 'all',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        array $filters = [],
    ): array {
        $normalizedFilters = $this->normalizeTraderDashboardFilters($user, $filters);
        $resolvedPeriod = $this->resolvePeriodRange($periodPreset, $dateFrom, $dateTo);
        $startDate = $resolvedPeriod['startDate'];
        $endDate = $resolvedPeriod['endDate'];

        if ($resolvedPeriod['preset'] === 'all') {
            $allBoundsQuery = Order::query();
            $this->scopeTraderOrders($allBoundsQuery, $user);
            $this->applyTraderDashboardOrderFilters($allBoundsQuery, $normalizedFilters);

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
            : 'DATE(created_at)';

        $query = Order::query()
            ->where('status', OrderStatus::SUCCESS)
            ->whereBetween('created_at', [$startDate, $endDate]);
        $this->scopeTraderOrders($query, $user);
        $this->applyTraderDashboardOrderFilters($query, $normalizedFilters);

        $totalTurnover = Money::fromUnits($query->clone()->sum('total_profit'), Currency::USDT());
        $totalProfit = Money::fromUnits($query->clone()->sum('trader_profit'), Currency::USDT());

        $successOrderQuery = Order::query()
            ->where('status', OrderStatus::SUCCESS)
            ->whereBetween('created_at', [$startDate, $endDate]);
        $this->scopeTraderOrders($successOrderQuery, $user);
        $this->applyTraderDashboardOrderFilters($successOrderQuery, $normalizedFilters);
        $successOrderCount = $successOrderQuery->count();

        $failedOrderQuery = Order::query()
            ->where('status', OrderStatus::FAIL)
            ->whereBetween('created_at', [$startDate, $endDate]);
        $this->scopeTraderOrders($failedOrderQuery, $user);
        $this->applyTraderDashboardOrderFilters($failedOrderQuery, $normalizedFilters);
        $failedOrderCount = $failedOrderQuery->count();

        $totalOrderCount = $successOrderCount + $failedOrderCount;
        $conversionRate = $totalOrderCount > 0
            ? round(($successOrderCount / $totalOrderCount) * 100, 2)
            : 0;

        $earningsByDayQuery = Order::where('status', OrderStatus::SUCCESS)
            ->whereBetween('created_at', [$startDate, $endDate]);
        $this->scopeTraderOrders($earningsByDayQuery, $user);
        $this->applyTraderDashboardOrderFilters($earningsByDayQuery, $normalizedFilters);
        $earningsByDay = $earningsByDayQuery
            ->selectRaw("{$bucketSql} as bucket_key, SUM(trader_profit) as total_earnings")
            ->groupBy('bucket_key')
            ->pluck('total_earnings', 'bucket_key');

        $turnoverByDayQuery = Order::where('status', OrderStatus::SUCCESS)
            ->whereBetween('created_at', [$startDate, $endDate]);
        $this->scopeTraderOrders($turnoverByDayQuery, $user);
        $this->applyTraderDashboardOrderFilters($turnoverByDayQuery, $normalizedFilters);
        $turnoverByDay = $turnoverByDayQuery
            ->selectRaw("{$bucketSql} as bucket_key, SUM(total_profit) as total_turnover")
            ->groupBy('bucket_key')
            ->pluck('total_turnover', 'bucket_key');

        $successOrdersByDayQuery = Order::where('status', OrderStatus::SUCCESS)
            ->whereBetween('created_at', [$startDate, $endDate]);
        $this->scopeTraderOrders($successOrdersByDayQuery, $user);
        $this->applyTraderDashboardOrderFilters($successOrdersByDayQuery, $normalizedFilters);
        $successOrdersByDay = $successOrdersByDayQuery
            ->selectRaw("{$bucketSql} as bucket_key, COUNT(*) as count")
            ->groupBy('bucket_key')
            ->pluck('count', 'bucket_key');

        $failedOrdersByDayQuery = Order::where('status', OrderStatus::FAIL)
            ->whereBetween('created_at', [$startDate, $endDate]);
        $this->scopeTraderOrders($failedOrdersByDayQuery, $user);
        $this->applyTraderDashboardOrderFilters($failedOrdersByDayQuery, $normalizedFilters);
        $failedOrdersByDay = $failedOrdersByDayQuery
            ->selectRaw("{$bucketSql} as bucket_key, COUNT(*) as count")
            ->groupBy('bucket_key')
            ->pluck('count', 'bucket_key');

        $totalAmountByDayQuery = Order::query()
            ->whereIn('status', [OrderStatus::SUCCESS, OrderStatus::FAIL])
            ->whereBetween('created_at', [$startDate, $endDate]);
        $this->scopeTraderOrders($totalAmountByDayQuery, $user);
        $this->applyTraderDashboardOrderFilters($totalAmountByDayQuery, $normalizedFilters);
        $totalAmountByDay = $totalAmountByDayQuery
            ->selectRaw("{$bucketSql} as bucket_key, SUM(total_profit) as total_amount")
            ->groupBy('bucket_key')
            ->pluck('total_amount', 'bucket_key');

        $labels = [];
        $incomeData = [];
        $turnoverData = [];
        $conversionData = [];
        $ordersData = [];
        $averageCheckData = [];

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
            $totalAmount = Money::fromUnits(
                (int) ($totalAmountByDay[$bucketKey] ?? 0),
                Currency::USDT()
            )->toInt();
            $buckets[] = [
                'label' => $label,
                'income' => $income,
                'turnover' => $turnover,
                'successCount' => $successCount,
                'failedCount' => $failedCount,
                'totalAmount' => $totalAmount,
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
                    'totalAmount' => array_sum(array_column($chunk, 'totalAmount')),
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
            $averageCheckData[] = $totalCount > 0
                ? round($bucket['totalAmount'] / $totalCount, 2)
                : 0;
            $conversionData[] = $totalCount > 0
                ? round(($bucket['successCount'] / $totalCount) * 100, 2)
                : 0;
        }

        $pendingOrdersQuery = Order::query()
            ->where('status', OrderStatus::PENDING)
            ->whereBetween('created_at', [$startDate, $endDate]);
        $this->scopeTraderOrders($pendingOrdersQuery, $user);
        $this->applyTraderDashboardOrderFilters($pendingOrdersQuery, $normalizedFilters);
        $pendingOrderCount = $pendingOrdersQuery->count();

        return [
            'statistics' => [
                'totalTurnover' => $totalTurnover->toBeauty(),
                'totalProfit' => $totalProfit->toBeauty(),
                'totalOrderCount' => $totalOrderCount,
                'successOrderCount' => $successOrderCount,
                'failedOrderCount' => $failedOrderCount,
                'conversionRate' => $conversionRate.'%',
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
            'averageCheckChart' => [
                'labels' => $labels,
                'data' => $averageCheckData,
            ],
            'selectedPeriodPreset' => $resolvedPeriod['preset'],
            'selectedDateFrom' => $resolvedPeriod['dateFrom'],
            'selectedDateTo' => $resolvedPeriod['dateTo'],
            'selectedFilters' => $normalizedFilters,
        ];
    }

    public function buildTraderPayoutMainPageStats(
        User $user,
        string $periodPreset = 'all',
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        $resolvedPeriod = $this->resolvePeriodRange($periodPreset, $dateFrom, $dateTo);
        $startDate = $resolvedPeriod['startDate'];
        $endDate = $resolvedPeriod['endDate'];

        $completedAtExpression = 'COALESCE(completed_at, updated_at)';

        if ($resolvedPeriod['preset'] === 'all') {
            $allBoundsQuery = Payout::query()
                ->where('trader_id', $user->id)
                ->where('status', PayoutStatus::COMPLETED->value);

            $minCompletedAt = $allBoundsQuery->min(DB::raw($completedAtExpression));
            if ($minCompletedAt) {
                $startDate = Carbon::parse($minCompletedAt)->startOfDay();
            } else {
                $startDate = now()->startOfDay();
            }

            $endDate = now()->endOfDay();
            $resolvedPeriod['dateFrom'] = $startDate->toDateString();
            $resolvedPeriod['dateTo'] = $endDate->toDateString();
        }

        $isHourly = $startDate->isSameDay($endDate);
        $bucketSql = $isHourly
            ? "DATE_FORMAT({$completedAtExpression}, '%Y-%m-%d %H:00:00')"
            : "DATE({$completedAtExpression})";

        $applyCompletedBetween = static function (Builder $query) use ($startDate, $endDate, $completedAtExpression): void {
            $query->whereRaw("{$completedAtExpression} between ? and ?", [$startDate, $endDate]);
        };

        $basePayoutQuery = static fn (): Builder => Payout::query()
            ->where('trader_id', $user->id)
            ->where('status', PayoutStatus::COMPLETED->value);

        $aggregatedQuery = $basePayoutQuery();
        $applyCompletedBetween($aggregatedQuery);

        $totalTurnover = Money::fromUnits(
            (int) $aggregatedQuery->clone()->sum(DB::raw('CAST(IFNULL(usdt_body, 0) AS SIGNED)')),
            Currency::USDT(),
        );
        $totalProfit = Money::fromUnits(
            (int) $aggregatedQuery->clone()->sum(DB::raw('CAST(IFNULL(trader_fee, 0) AS SIGNED)')),
            Currency::USDT(),
        );
        $successPayoutCount = (int) $aggregatedQuery->clone()->count();

        $earningsByBucket = $basePayoutQuery();
        $applyCompletedBetween($earningsByBucket);
        $earningsByBucket = $earningsByBucket
            ->selectRaw("{$bucketSql} as bucket_key, SUM(CAST(IFNULL(trader_fee, 0) AS SIGNED)) as total_earnings")
            ->groupBy(DB::raw($bucketSql))
            ->pluck('total_earnings', 'bucket_key');

        $turnoverByBucket = $basePayoutQuery();
        $applyCompletedBetween($turnoverByBucket);
        $turnoverByBucket = $turnoverByBucket
            ->selectRaw("{$bucketSql} as bucket_key, SUM(CAST(IFNULL(usdt_body, 0) AS SIGNED)) as total_turnover")
            ->groupBy(DB::raw($bucketSql))
            ->pluck('total_turnover', 'bucket_key');

        $countByBucket = $basePayoutQuery();
        $applyCompletedBetween($countByBucket);
        $countByBucket = $countByBucket
            ->selectRaw("{$bucketSql} as bucket_key, COUNT(*) as cnt")
            ->groupBy(DB::raw($bucketSql))
            ->pluck('cnt', 'bucket_key');

        $labels = [];
        $incomeData = [];
        $turnoverData = [];
        $ordersData = [];
        $averageCheckData = [];

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
                (int) ($earningsByBucket[$bucketKey] ?? 0),
                Currency::USDT()
            )->toInt();
            $turnover = Money::fromUnits(
                (int) ($turnoverByBucket[$bucketKey] ?? 0),
                Currency::USDT()
            )->toInt();
            $payoutCount = (int) ($countByBucket[$bucketKey] ?? 0);

            $buckets[] = [
                'label' => $label,
                'income' => $income,
                'turnover' => $turnover,
                'payoutCount' => $payoutCount,
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
                    'payoutCount' => array_sum(array_column($chunk, 'payoutCount')),
                ];
            }

            $buckets = $groupedBuckets;
        }

        foreach ($buckets as $bucket) {
            $labels[] = $bucket['label'];
            $incomeData[] = $bucket['income'];
            $turnoverData[] = $bucket['turnover'];
            $ordersData[] = $bucket['payoutCount'];
            $averageCheckData[] = $bucket['payoutCount'] > 0
                ? round($bucket['turnover'] / $bucket['payoutCount'], 2)
                : 0;
        }

        $emptyFilters = [
            'paymentMethodIds' => [],
            'paymentDetailIds' => [],
        ];

        return [
            'statistics' => [
                'totalTurnover' => $totalTurnover->toBeauty(),
                'totalProfit' => $totalProfit->toBeauty(),
                'successPayoutCount' => $successPayoutCount,
            ],
            'chart' => [
                'labels' => $labels,
                'data' => $incomeData,
            ],
            'conversionChart' => [
                'labels' => [],
                'data' => [],
            ],
            'turnoverChart' => [
                'labels' => $labels,
                'data' => $turnoverData,
            ],
            'ordersChart' => [
                'labels' => $labels,
                'data' => $ordersData,
            ],
            'averageCheckChart' => [
                'labels' => $labels,
                'data' => $averageCheckData,
            ],
            'selectedPeriodPreset' => $resolvedPeriod['preset'],
            'selectedDateFrom' => $resolvedPeriod['dateFrom'],
            'selectedDateTo' => $resolvedPeriod['dateTo'],
            'selectedFilters' => $emptyFilters,
        ];
    }

    public function buildMerchantMainPageStats(
        User $user,
        string $periodPreset = 'all',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        array $filters = [],
    ): array {
        $normalizedFilters = $this->normalizeMerchantDashboardFilters($user, $filters);
        $resolvedPeriod = $this->resolvePeriodRange($periodPreset, $dateFrom, $dateTo);
        $startDate = $resolvedPeriod['startDate'];
        $endDate = $resolvedPeriod['endDate'];

        if ($resolvedPeriod['preset'] === 'all') {
            $allBoundsQuery = Order::query();
            $this->scopeMerchantOrders($allBoundsQuery, $user, $normalizedFilters['merchantIds']);
            $this->applyMerchantDashboardOrderFilters($allBoundsQuery, $normalizedFilters);

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
            : 'DATE(created_at)';

        $query = Order::query()
            ->where('status', OrderStatus::SUCCESS)
            ->whereBetween('created_at', [$startDate, $endDate]);
        $this->scopeMerchantOrders($query, $user, $normalizedFilters['merchantIds']);
        $this->applyMerchantDashboardOrderFilters($query, $normalizedFilters);

        $totalTurnover = Money::fromUnits($query->clone()->sum('total_profit'), Currency::USDT());
        $totalProfit = Money::fromUnits($query->clone()->sum('merchant_profit'), Currency::USDT());

        $successOrderQuery = Order::query()
            ->where('status', OrderStatus::SUCCESS)
            ->whereBetween('created_at', [$startDate, $endDate]);
        $this->scopeMerchantOrders($successOrderQuery, $user, $normalizedFilters['merchantIds']);
        $this->applyMerchantDashboardOrderFilters($successOrderQuery, $normalizedFilters);
        $successOrderCount = $successOrderQuery->count();

        $failedOrderQuery = Order::query()
            ->where('status', OrderStatus::FAIL)
            ->whereBetween('created_at', [$startDate, $endDate]);
        $this->scopeMerchantOrders($failedOrderQuery, $user, $normalizedFilters['merchantIds']);
        $this->applyMerchantDashboardOrderFilters($failedOrderQuery, $normalizedFilters);
        $failedOrderCount = $failedOrderQuery->count();

        $totalOrderCount = $successOrderCount + $failedOrderCount;
        $conversionRate = $totalOrderCount > 0
            ? round(($successOrderCount / $totalOrderCount) * 100, 2)
            : 0;

        $earningsByDayQuery = Order::where('status', OrderStatus::SUCCESS)
            ->whereBetween('created_at', [$startDate, $endDate]);
        $this->scopeMerchantOrders($earningsByDayQuery, $user, $normalizedFilters['merchantIds']);
        $this->applyMerchantDashboardOrderFilters($earningsByDayQuery, $normalizedFilters);
        $earningsByDay = $earningsByDayQuery
            ->selectRaw("{$bucketSql} as bucket_key, SUM(merchant_profit) as total_earnings")
            ->groupBy('bucket_key')
            ->pluck('total_earnings', 'bucket_key');

        $turnoverByDayQuery = Order::where('status', OrderStatus::SUCCESS)
            ->whereBetween('created_at', [$startDate, $endDate]);
        $this->scopeMerchantOrders($turnoverByDayQuery, $user, $normalizedFilters['merchantIds']);
        $this->applyMerchantDashboardOrderFilters($turnoverByDayQuery, $normalizedFilters);
        $turnoverByDay = $turnoverByDayQuery
            ->selectRaw("{$bucketSql} as bucket_key, SUM(total_profit) as total_turnover")
            ->groupBy('bucket_key')
            ->pluck('total_turnover', 'bucket_key');

        $successOrdersByDayQuery = Order::where('status', OrderStatus::SUCCESS)
            ->whereBetween('created_at', [$startDate, $endDate]);
        $this->scopeMerchantOrders($successOrdersByDayQuery, $user, $normalizedFilters['merchantIds']);
        $this->applyMerchantDashboardOrderFilters($successOrdersByDayQuery, $normalizedFilters);
        $successOrdersByDay = $successOrdersByDayQuery
            ->selectRaw("{$bucketSql} as bucket_key, COUNT(*) as count")
            ->groupBy('bucket_key')
            ->pluck('count', 'bucket_key');

        $failedOrdersByDayQuery = Order::where('status', OrderStatus::FAIL)
            ->whereBetween('created_at', [$startDate, $endDate]);
        $this->scopeMerchantOrders($failedOrdersByDayQuery, $user, $normalizedFilters['merchantIds']);
        $this->applyMerchantDashboardOrderFilters($failedOrdersByDayQuery, $normalizedFilters);
        $failedOrdersByDay = $failedOrdersByDayQuery
            ->selectRaw("{$bucketSql} as bucket_key, COUNT(*) as count")
            ->groupBy('bucket_key')
            ->pluck('count', 'bucket_key');

        $totalAmountByDayQuery = Order::query()
            ->whereIn('status', [OrderStatus::SUCCESS, OrderStatus::FAIL])
            ->whereBetween('created_at', [$startDate, $endDate]);
        $this->scopeMerchantOrders($totalAmountByDayQuery, $user, $normalizedFilters['merchantIds']);
        $this->applyMerchantDashboardOrderFilters($totalAmountByDayQuery, $normalizedFilters);
        $totalAmountByDay = $totalAmountByDayQuery
            ->selectRaw("{$bucketSql} as bucket_key, SUM(total_profit) as total_amount")
            ->groupBy('bucket_key')
            ->pluck('total_amount', 'bucket_key');

        $labels = [];
        $incomeData = [];
        $turnoverData = [];
        $conversionData = [];
        $ordersData = [];
        $averageCheckData = [];

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
            $totalAmount = Money::fromUnits(
                (int) ($totalAmountByDay[$bucketKey] ?? 0),
                Currency::USDT()
            )->toInt();
            $buckets[] = [
                'label' => $label,
                'income' => $income,
                'turnover' => $turnover,
                'successCount' => $successCount,
                'failedCount' => $failedCount,
                'totalAmount' => $totalAmount,
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
                    'totalAmount' => array_sum(array_column($chunk, 'totalAmount')),
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
            $averageCheckData[] = $totalCount > 0
                ? round($bucket['totalAmount'] / $totalCount, 2)
                : 0;
            $conversionData[] = $totalCount > 0
                ? round(($bucket['successCount'] / $totalCount) * 100, 2)
                : 0;
        }

        $pendingOrdersQuery = Order::query()
            ->where('status', OrderStatus::PENDING)
            ->whereBetween('created_at', [$startDate, $endDate]);
        $this->scopeMerchantOrders($pendingOrdersQuery, $user, $normalizedFilters['merchantIds']);
        $this->applyMerchantDashboardOrderFilters($pendingOrdersQuery, $normalizedFilters);
        $pendingOrderCount = $pendingOrdersQuery->count();

        return [
            'statistics' => [
                'totalTurnover' => $totalTurnover->toBeauty(),
                'totalProfit' => $totalProfit->toBeauty(),
                'totalOrderCount' => $totalOrderCount,
                'successOrderCount' => $successOrderCount,
                'failedOrderCount' => $failedOrderCount,
                'conversionRate' => $conversionRate.'%',
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
            'averageCheckChart' => [
                'labels' => $labels,
                'data' => $averageCheckData,
            ],
            'selectedPeriodPreset' => $resolvedPeriod['preset'],
            'selectedDateFrom' => $resolvedPeriod['dateFrom'],
            'selectedDateTo' => $resolvedPeriod['dateTo'],
            'selectedFilters' => $normalizedFilters,
        ];
    }

    public function buildMerchantPayoutMainPageStats(
        User $user,
        string $periodPreset = 'all',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        array $filters = [],
    ): array {
        $allowedMerchantIds = $this->resolveUserMerchantIds($user);
        $selectedMerchantIds = $this->normalizeIdArray($filters['merchantIds'] ?? []);

        if (! empty($selectedMerchantIds)) {
            $selectedMerchantIds = array_values(array_intersect($selectedMerchantIds, $allowedMerchantIds));
        }

        $merchantIds = ! empty($selectedMerchantIds) ? array_slice($selectedMerchantIds, 0, 1) : $allowedMerchantIds;
        if (empty($merchantIds)) {
            $merchantIds = [0];
        }

        $stats = $this->buildAdminPayoutMainPageStats(
            $user,
            null,
            $periodPreset,
            $dateFrom,
            $dateTo,
            [
                'merchantIds' => $merchantIds,
                'traderIds' => [],
            ],
        );

        $stats['selectedFilters'] = $this->normalizeFilters([
            'merchantIds' => ! empty($selectedMerchantIds) ? $merchantIds : [],
            'paymentMethodIds' => [],
            'paymentDetailIds' => [],
            'traderIds' => [],
        ]);

        return $stats;
    }

    private function scopeTraderOrders(Builder $query, User $user): void
    {
        $query->whereRelation('paymentDetail', 'user_id', $user->id);
    }

    private function scopeMerchantOrders(Builder $query, User $user, array $merchantIds = []): void
    {
        $allowedMerchantIds = $this->resolveUserMerchantIds($user);

        if (! empty($merchantIds)) {
            $allowedSet = array_flip($allowedMerchantIds);
            $scopedMerchantIds = array_values(array_filter(
                $merchantIds,
                fn (int $id) => isset($allowedSet[$id])
            ));
            $query->whereIn('merchant_id', $scopedMerchantIds);

            return;
        }

        $query->whereIn('merchant_id', $allowedMerchantIds);
    }

    private function normalizeTraderDashboardFilters(User $user, array $filters): array
    {
        $allowedDetailIds = PaymentDetail::query()
            ->where('user_id', $user->id)
            ->pluck('id')
            ->all();

        $allowedDetailIdSet = array_flip($allowedDetailIds);

        $detailIds = array_values(array_filter(
            $this->normalizeIdArray($filters['paymentDetailIds'] ?? []),
            fn (int $id) => isset($allowedDetailIdSet[$id])
        ));

        $gatewayIdsFromDetails = $allowedDetailIds === []
            ? []
            : DB::table('payment_detail_payment_gateway')
                ->whereIn('payment_detail_id', $allowedDetailIds)
                ->distinct()
                ->pluck('payment_gateway_id')
                ->all();

        $gatewaySet = array_flip(array_map('intval', $gatewayIdsFromDetails));

        $methodIds = array_values(array_filter(
            $this->normalizeIdArray($filters['paymentMethodIds'] ?? []),
            fn (int $id) => isset($gatewaySet[$id])
        ));

        return [
            'paymentMethodIds' => $methodIds,
            'paymentDetailIds' => $detailIds,
        ];
    }

    private function applyTraderDashboardOrderFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['paymentMethodIds'])) {
            $query->whereIn('payment_gateway_id', $filters['paymentMethodIds']);
        }

        if (! empty($filters['paymentDetailIds'])) {
            $query->whereIn('payment_detail_id', $filters['paymentDetailIds']);
        }
    }

    private function normalizeMerchantDashboardFilters(User $user, array $filters): array
    {
        $allowedMerchantIds = $this->resolveUserMerchantIds($user);
        $allowedMerchantSet = array_flip($allowedMerchantIds);

        $merchantIds = array_values(array_filter(
            $this->normalizeIdArray($filters['merchantIds'] ?? []),
            fn (int $id) => isset($allowedMerchantSet[$id])
        ));

        $allowedGatewayIds = empty($allowedMerchantIds)
            ? []
            : Order::query()
                ->whereIn('merchant_id', $allowedMerchantIds)
                ->distinct()
                ->pluck('payment_gateway_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        $gatewaySet = array_flip($allowedGatewayIds);

        $methodIds = array_values(array_filter(
            $this->normalizeIdArray($filters['paymentMethodIds'] ?? []),
            fn (int $id) => isset($gatewaySet[$id])
        ));

        return [
            'paymentMethodIds' => $methodIds,
            'merchantIds' => $merchantIds,
        ];
    }

    private function applyMerchantDashboardOrderFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['paymentMethodIds'])) {
            $query->whereIn('payment_gateway_id', $filters['paymentMethodIds']);
        }
    }

    private function resolveUserMerchantIds(User $user): array
    {
        return Merchant::query()
            ->where('user_id', $user->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
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

    /**
     * @param  array{traderIds?: array<int>, merchantIds?: array<int>}  $filters
     * @return array{traderIds: array<int>, merchantIds: array<int>}
     */
    private function normalizeAdminPayoutFilters(array $filters): array
    {
        return [
            'traderIds' => $this->normalizeIdArray($filters['traderIds'] ?? []),
            'merchantIds' => $this->normalizeIdArray($filters['merchantIds'] ?? []),
        ];
    }

    /**
     * @param  array{traderIds: array<int>, merchantIds: array<int>}  $filters
     */
    private function applyAdminPayoutFilters(Builder $query, ?int $scopedMerchantId, array $filters): void
    {
        if ($scopedMerchantId) {
            $query->where('merchant_id', $scopedMerchantId);
        }

        if (! empty($filters['traderIds'])) {
            $query->whereIn('trader_id', $filters['traderIds']);
        }

        if (! empty($filters['merchantIds'])) {
            $query->whereIn('merchant_id', $filters['merchantIds']);
        }
    }

    private function normalizeIdArray(mixed $value): array
    {
        if (! is_array($value)) {
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
        if (! empty($filters['traderIds'])) {
            $query->whereIn('trader_id', $filters['traderIds']);
        }

        if (! empty($filters['paymentMethodIds'])) {
            $query->whereIn('payment_gateway_id', $filters['paymentMethodIds']);
        }

        if (! empty($filters['paymentDetailIds'])) {
            $query->whereIn('payment_detail_id', $filters['paymentDetailIds']);
        }

        if (! empty($filters['merchantIds'])) {
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
        $monthAnchorDate = $this->parseDateValue($dateFrom)
            ?? $this->parseDateValue($dateTo)
            ?? $now->copy();
        $startDate = $monthAnchorDate->copy()->startOfMonth()->startOfDay();
        $endDate = $monthAnchorDate->copy()->endOfMonth()->endOfDay();

        if ($normalizedPreset === 'today') {
            $dayAnchorDate = $this->parseDateValue($dateFrom)
                ?? $this->parseDateValue($dateTo)
                ?? $now->copy();
            $startDate = $dayAnchorDate->copy()->startOfDay();
            $endDate = $dayAnchorDate->copy()->endOfDay();
        } elseif ($normalizedPreset === 'week') {
            $weekAnchorDate = $this->parseDateValue($dateFrom)
                ?? $this->parseDateValue($dateTo)
                ?? $now->copy();
            $startDate = $weekAnchorDate->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $endDate = $weekAnchorDate->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
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
        if (! $value) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }
    }
}
