<?php

namespace App\Http\Controllers\Analyst;

use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TableOrderResource;
use App\Models\Order;
use App\Services\Money\Money;
use App\Services\Order\OrderTraderDebitService;
use App\Utils\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index()
    {
        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $orders = queries()->order()->paginateForAdmin($filters);
        $orders = TableOrderResource::collection($orders);

        return Inertia::render('Analyst/Order/Index', compact('orders', 'filters', 'filtersVariants'));
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

        $order->trader->loadMissing(['wallet', 'teamLeader.wallet']);

        $balance = app(OrderTraderDebitService::class)->getAvailableDebitBalance($order->trader);

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

    public function updateAmount(Request $request, Order $order)
    {
        abort_unless((bool) $request->user()?->support_can_edit_order_amount, 403);

        Gate::authorize('access-to-order', $order);

        $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        services()->order()->updateAmount(
            orderID: $order->id,
            amount: Money::fromPrecision($request->input('amount'), $order->currency),
        );

        return redirect()->back()->with('message', 'Сумма сделки обновлена.');
    }
}
