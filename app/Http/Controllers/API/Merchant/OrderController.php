<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\Merchant\Order\StoreRequest;
use App\Http\Resources\API\Merchant\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function show(Order $order): JsonResponse
    {
        if ($order->is_h2h) {
            return response()->failWithMessage('Сделка предназначена для H2H API.');
        }

        Gate::authorize('access-to-order', $order);

        return response()->success(
            OrderResource::make($order)
        );
    }

    public function showByExternal(string $merchant_id, string $external_id): JsonResponse
    {
        $order = Order::query()
            ->whereRelation('merchant', 'uuid', $merchant_id)
            ->where('external_id', $external_id)
            ->firstOrFail();

        if ($order->is_h2h) {
            return response()->failWithMessage('Сделка предназначена для H2H API.');
        }

        Gate::authorize('access-to-order', $order);

        return response()->success(
            OrderResource::make($order)
        );
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $merchant = queries()->merchant()->findByUUID($request->merchant_id);

        Gate::authorize('api-access-to-merchant', $merchant);

        return services()->orderPooling()->processOrderPooling($request);
    }
}
