<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TableOrderResource;
use App\Models\Order;
use App\Services\Money\Money;
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

        return Inertia::render('Order/Index', compact('orders', 'filters', 'filtersVariants'));
    }

    public function updateAmount(Request $request, Order $order)
    {
        Gate::authorize('access-to-order', $order);

        $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        if (auth()->user()->hasRole('Trader')) {
            $fivePercent = $order->amount->div(20)->toInt();
            $changedAmount = $order->amount->sub($request->amount)->abs()->toInt();
            if ($changedAmount > $fivePercent) {
                return redirect()->back()->with('error', "Сумму можно изменить не более чем на 5% ($fivePercent {$order->currency->getSymbol()})");
            }
        }

        services()->order()->updateAmount(
            orderID: $order->id,
            amount: Money::fromPrecision($request->input('amount'), $order->currency),
        );

        return redirect()->back()->with('message', 'Сумма сделки обновлена.');
    }

}
