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

    public function buildAdminStats(User $user, ?int $merchantId = null): array
    {
        $query = Order::query()
            ->where('status', OrderStatus::SUCCESS);

        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }

        $totalTurnover = Money::fromUnits($query->clone()->sum('total_profit'), Currency::USDT());
        $totalProfit = Money::fromUnits($query->clone()->sum('service_profit'), Currency::USDT());

        $successOrderQuery = Order::query()
            ->where('status', OrderStatus::SUCCESS);

        if ($merchantId) {
            $successOrderQuery->where('merchant_id', $merchantId);
        }

        $successOrderCount = $successOrderQuery->count();

        $failedOrderQuery = Order::query()
            ->where('status', OrderStatus::FAIL);

        if ($merchantId) {
            $failedOrderQuery->where('merchant_id', $merchantId);
        }

        $failedOrderCount = $failedOrderQuery->count();

        $totalOrderCount = $successOrderCount + $failedOrderCount;
        $conversionRate = $totalOrderCount > 0
            ? round(($successOrderCount / $totalOrderCount) * 100, 2)
            : 0;

        $startDate = now()->subDays(29);
        $endDate = now();

        $earningsByDayQuery = Order::where('status', OrderStatus::SUCCESS)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($merchantId) {
            $earningsByDayQuery->where('merchant_id', $merchantId);
        }

        $earningsByDay = $earningsByDayQuery
            ->selectRaw('DATE(created_at) as date, SUM(service_profit) as total_earnings')
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

        $conversionData = [];

        $successOrdersByDayQuery = Order::where('status', OrderStatus::SUCCESS)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($merchantId) {
            $successOrdersByDayQuery->where('merchant_id', $merchantId);
        }

        $successOrdersByDay = $successOrdersByDayQuery
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $failedOrdersByDayQuery = Order::where('status', OrderStatus::FAIL)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($merchantId) {
            $failedOrdersByDayQuery->where('merchant_id', $merchantId);
        }

        $failedOrdersByDay = $failedOrdersByDayQuery
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        for ($i = 0; $i < 30; $i++) {
            $date = $startDate->copy()->addDays($i)->toDateString();
            $successCount = $successOrdersByDay[$date] ?? 0;
            $failedCount = $failedOrdersByDay[$date] ?? 0;
            $totalCount = $successCount + $failedCount;

            $conversionData[] = $totalCount > 0
                ? round(($successCount / $totalCount) * 100, 2)
                : 0;
        }

        $hourlyConversionData = [];
        $hourlyLabels = [];

        $hourlyStartDate = now()->subHours(23);
        $hourlyEndDate = now();

        $successOrdersByHourQuery = Order::where('status', OrderStatus::SUCCESS)
            ->whereBetween('created_at', [$hourlyStartDate, $hourlyEndDate]);

        if ($merchantId) {
            $successOrdersByHourQuery->where('merchant_id', $merchantId);
        }

        $successOrdersByHour = $successOrdersByHourQuery
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->pluck('count', 'hour');

        $failedOrdersByHourQuery = Order::where('status', OrderStatus::FAIL)
            ->whereBetween('created_at', [$hourlyStartDate, $hourlyEndDate]);

        if ($merchantId) {
            $failedOrdersByHourQuery->where('merchant_id', $merchantId);
        }

        $failedOrdersByHour = $failedOrdersByHourQuery
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->pluck('count', 'hour');

        for ($i = 0; $i < 24; $i++) {
            $hour = ($hourlyStartDate->copy()->addHours($i))->hour;
            $hourlyLabels[] = $hour;

            $successCount = $successOrdersByHour[$hour] ?? 0;
            $failedCount = $failedOrdersByHour[$hour] ?? 0;
            $totalCount = $successCount + $failedCount;

            $hourlyConversionData[] = $totalCount > 0
                ? round(($successCount / $totalCount) * 100, 2)
                : 0;
        }

        $pendingOrdersQuery = Order::query()
            ->where('status', OrderStatus::PENDING);

        if ($merchantId) {
            $pendingOrdersQuery->where('merchant_id', $merchantId);
        }

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
                'data' => $data,
            ],
            'conversionChart' => [
                'labels' => $labels,
                'data' => $conversionData,
            ],
            'hourlyConversionChart' => [
                'labels' => $hourlyLabels,
                'data' => $hourlyConversionData,
            ],
        ];
    }
}

