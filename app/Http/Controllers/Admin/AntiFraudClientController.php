<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MerchantClientResource;
use App\Models\MerchantClient;
use App\Models\Order;
use App\Services\Money\Currency;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AntiFraudClientController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $clientsQuery = MerchantClient::query()
            ->with('merchant:id,name,uuid')
            ->when($filters->merchantIds, function ($query, array $merchantIds) {
                $query->whereIn('merchant_id', $merchantIds);
            })
            ->when($filters->orderUuid, function ($query, string $orderUuid) {
                $query->whereHas('orders', function ($ordersQuery) use ($orderUuid) {
                    $ordersQuery->where('uuid', 'like', "%{$orderUuid}%");
                });
            })
            ->when($filters->clientId, function ($query, string $clientId) {
                $query->where('client_id', 'like', "%{$clientId}%");
            })
            ->withCount([
                'orders as success_orders_count' => function ($query) {
                    $query->where('status', OrderStatus::SUCCESS);
                },
                'orders as total_orders_count',
            ]);

        $clients = $clientsQuery
            ->orderByDesc('id')
            ->paginate($request->get('per_page', 10))
            ->withQueryString();

        $clients = MerchantClientResource::collection($clients);

        return Inertia::render('Admin/AntiFraud/Clients', [
            'clients' => $clients,
            'filters' => $filters->toArray(),
            'filtersVariants' => $filtersVariants,
        ]);
    }

    public function orders(MerchantClient $merchantClient)
    {
        $orders = Order::query()
            ->where('merchant_client_id', $merchantClient->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'uuid' => $order->uuid,
                'amount' => $order->amount->toBeauty(),
                'total_profit' => $order->total_profit->toBeauty(),
                'currency' => $order->currency->getCode(),
                'base_currency' => Currency::USDT()->getCode(),
                'status' => $order->status->value,
                'status_name' => $order->status_name,
                'created_at' => $order->created_at->toISOString(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }
}
