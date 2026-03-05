<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BalanceType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserBalanceResource;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UserBalanceController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->getTableFilters();

        // Получаем ID пользователей для текущей страницы
        $usersQuery = User::query()
            ->with(['roles', 'wallet'])
            ->when($filters->user, function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->where('email', 'like', '%' . $filters->user . '%');
                    $query->orWhere('name', 'like', '%' . $filters->user . '%');
                });
            })
            ->orderByDesc('id');

        $usersPaginator = $usersQuery->paginate(request()->per_page ?? 10);
        $userIds = $usersPaginator->pluck('id')->toArray();

        // Получаем все необходимые данные по инвойсам для пользователей
        $invoiceStats = Invoice::query()
            ->whereIn('wallet_id', function ($query) use ($userIds) {
                $query->select('id')
                    ->from('wallets')
                    ->whereIn('user_id', $userIds);
            })
            ->where('status', InvoiceStatus::SUCCESS)
            ->select(
                'wallet_id',
                'type',
                'balance_type',
                DB::raw('SUM(amount) as total_amount')
            )
            ->groupBy('wallet_id', 'type', 'balance_type')
            ->get();

        // Преобразуем результаты в удобный формат
        $userBalanceStats = [];
        foreach ($invoiceStats as $stat) {
            $walletId = $stat->wallet_id;
            if (!isset($userBalanceStats[$walletId])) {
                $userBalanceStats[$walletId] = [
                    'trust_deposits' => 0,
                    'trust_withdrawals' => 0,
                    'merchant_deposits' => 0,
                    'merchant_withdrawals' => 0,
                    'teamleader_deposits' => 0,
                    'teamleader_withdrawals' => 0,
                    'payment_for_orders' => 0,
                ];
            }

            // Определяем ключ для сохранения данных
            $key = strtolower($stat->balance_type->value) . '_' .
                  ($stat->type === InvoiceType::DEPOSIT ? 'deposits' : 'withdrawals');

            if (isset($userBalanceStats[$walletId][$key])) {
                $userBalanceStats[$walletId][$key] = $stat->total_amount;
            }
        }

        // Получаем данные по оплатам заказов
        $orderStats = Order::query()
            ->whereIn('trader_id', $userIds)
            ->where('status', OrderStatus::SUCCESS)
            ->select(
                'trader_id',
                DB::raw('SUM(CASE WHEN trader_paid_for_order IS NOT NULL THEN trader_paid_for_order ELSE total_profit END) as payment_for_orders')
            )
            ->groupBy('trader_id')
            ->get();

        // Добавляем данные по заказам
        foreach ($orderStats as $stat) {
            $traderId = $stat->trader_id;
            $user = $usersPaginator->firstWhere('id', $traderId);
            if ($user && $user->wallet) {
                $userBalanceStats[$user->wallet->id]['payment_for_orders'] = $stat->payment_for_orders;
            }
        }

        // Создаем ресурсы с дополнительными данными
        $users = UserBalanceResource::collection(
            $usersPaginator->through(function ($user) use ($userBalanceStats) {
                if ($user->wallet && isset($userBalanceStats[$user->wallet->id])) {
                    $user->balance_stats = $userBalanceStats[$user->wallet->id];
                } else {
                    $user->balance_stats = [
                        'trust_deposits' => 0,
                        'trust_withdrawals' => 0,
                        'merchant_deposits' => 0,
                        'merchant_withdrawals' => 0,
                        'teamleader_deposits' => 0,
                        'teamleader_withdrawals' => 0,
                        'payment_for_orders' => 0,
                    ];
                }
                return $user;
            })
        );

        // Получаем общую сумму всех балансов
        $totalTrustBalance = Money::fromPrecision(0, Currency::USDT());
        $totalMerchantBalance = Money::fromPrecision(0, Currency::USDT());

        User::query()
            ->with(['wallet'])
            ->chunk(100, function (Collection $users) use (&$totalTrustBalance, &$totalMerchantBalance) {
                $users->each(function ($user) use (&$totalTrustBalance, &$totalMerchantBalance) {
                    $totalTrustBalance = $totalTrustBalance->add($user->wallet->trust_balance)->add($user->wallet->reserve_balance);
                    $totalMerchantBalance = $totalMerchantBalance->add($user->wallet->merchant_balance);
                });
            });

        // Получаем общие суммы зачислений и выводов из базы данных
        $totalTrustDeposits = Invoice::query()
            ->where('status', InvoiceStatus::SUCCESS)
            ->where('type', InvoiceType::DEPOSIT)
            ->where('balance_type', BalanceType::TRUST)
            ->sum('amount');

        $totalTrustWithdrawals = Invoice::query()
            ->where('status', InvoiceStatus::SUCCESS)
            ->where('type', InvoiceType::WITHDRAWAL)
            ->where('balance_type', BalanceType::TRUST)
            ->sum('amount');

        $totalMerchantDeposits = Invoice::query()
            ->where('status', InvoiceStatus::SUCCESS)
            ->where('type', InvoiceType::DEPOSIT)
            ->where('balance_type', BalanceType::MERCHANT)
            ->sum('amount');

        $totalMerchantWithdrawals = Invoice::query()
            ->where('status', InvoiceStatus::SUCCESS)
            ->where('type', InvoiceType::WITHDRAWAL)
            ->where('balance_type', BalanceType::MERCHANT)
            ->sum('amount');

        $totalPaymentForOrders = Order::query()
            ->where('status', OrderStatus::SUCCESS)
            ->whereNotNull('trader_paid_for_order')
            ->sum('trader_paid_for_order');

        $totalPaymentForOrders = Order::query()
            ->where('status', OrderStatus::SUCCESS)
            ->whereNull('trader_paid_for_order')
            ->sum('total_profit') + $totalPaymentForOrders;

        $totals = [
            'trust_balance' => $totalTrustBalance->toBeauty(),
            'merchant_balance' => $totalMerchantBalance->toBeauty(),
            'total_balance' => $totalTrustBalance->add($totalMerchantBalance)->toBeauty(),
            'trust_deposits' => Money::fromUnits($totalTrustDeposits, Currency::USDT())->toBeauty(),
            'trust_withdrawals' => Money::fromUnits($totalTrustWithdrawals, Currency::USDT())->toBeauty(),
            'merchant_deposits' => Money::fromUnits($totalMerchantDeposits, Currency::USDT())->toBeauty(),
            'merchant_withdrawals' => Money::fromUnits($totalMerchantWithdrawals, Currency::USDT())->toBeauty(),
            'payment_for_orders' => Money::fromUnits($totalPaymentForOrders, Currency::USDT())->toBeauty(),
        ];

        return Inertia::render('UserBalance/Index', compact('users', 'filters', 'totals'));
    }
}
