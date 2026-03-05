<?php

namespace App\Http\Controllers;

use App\Enums\BalanceType;
use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Http\Resources\OrderResource;
use App\Http\Resources\TableOrderResource;
use App\Models\Order;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Utils\Transaction;
use Inertia\Inertia;
use Illuminate\Support\Facades\Gate;

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
        $order->load([
                'trader:id,name,email',
                'smsLog:id,sender,message,created_at,order_id',
                'paymentGateway:id,name,code,logo,currency',
                'paymentDetail:id,detail,detail_type,name,currency,created_at',
                'merchant:id,name',
                'teamLeader:id,name,email',
            ]);
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
