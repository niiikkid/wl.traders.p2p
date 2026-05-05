<?php

namespace App\Http\Controllers;

use App\Enums\BalanceType;
use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Http\Resources\OrderResource;
use App\Http\Resources\TableOrderResource;
use App\Models\Order;
use App\Utils\Transaction;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index()
    {
        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $orders = queries()->order()->paginateForUser(auth()->user(), $filters);
        $orders = TableOrderResource::collection($orders);

        return Inertia::render('Order/Index', compact('orders', 'filters', 'filtersVariants'));
    }

    public function show(Order $order)
    {
        $authUser = auth()->user();
        $loadWalletRelations = $authUser?->hasRole('Super Admin')
            && request()->input('view_mode') === 'admin';

        $with = [
            'trader:id,name,email',
            'smsLog:id,sender,message,created_at,order_id',
            'paymentGateway:id,name,code,logo,currency',
            'paymentDetail:id,detail,detail_type,name,additional_info,currency,created_at,user_device_id',
            'paymentDetail.userDevice:id,name',
            'merchant:id,name',
            'teamLeader:id,name,email',
            'manualControlTakenByUser:id,name,email',
            'manualControlConfirmationCodes' => fn ($query) => $query->orderByDesc('id'),
        ];

        if ($loadWalletRelations) {
            $with = array_merge($with, [
                'trader.wallet',
                'merchant.user.wallet',
                'teamLeader.wallet',
                'walletTransactions' => fn ($query) => $query->latest('id')->limit(50),
            ]);
        }

        $order->load($with);
        $order->loadExists('dispute');

        $order = OrderResource::make($order);

        return response()->success(compact('order'));
    }

    public function acceptOrder(Order $order)
    {
        Gate::authorize('access-to-order', $order);

        if ($order->dispute) {
            return;
        }

        if ($order->status->equals(OrderStatus::SUCCESS)) {
            return;
        }

        $balance = services()->wallet()->getTotalAvailableBalance(
            wallet: $order->trader->wallet,
            balanceType: BalanceType::TRUST,
        );

        if ($balance->lessThan($order->trader_paid_for_order) && $order->status->equals(OrderStatus::FAIL)) {
            return redirect()->back()->with('error', 'Не достаточно средств на балансе.');
        }

        Transaction::run(function () use ($order) {
            if ($order->status->equals(OrderStatus::FAIL)) {
                services()->order()->reopenFinishedOrder($order->id, OrderSubStatus::WAITING_FOR_PAYMENT);
            }

            services()->order()->finishOrderAsSuccessful($order->id, OrderSubStatus::ACCEPTED);
        });
    }
}
