<?php

namespace App\Http\Controllers\API\H2H;

use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\H2H\Order\StoreRequest;
use App\Http\Resources\API\H2H\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function show(Order $order): JsonResponse
    {
        if (! $order->is_h2h) {
            return response()->failWithMessage('Сделка предназначена не для H2H API, а для Merchant API.');
        }

        $order->load('dispute', 'paymentGateway', 'paymentDetail');

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

        if (! $order->is_h2h) {
            return response()->failWithMessage('Сделка предназначена не для H2H API, а для Merchant API.');
        }

        $order->load('dispute', 'paymentGateway', 'paymentDetail');

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

    public function finish(Order $order): JsonResponse
    {
        if (! $order->is_h2h) {
            return response()->failWithMessage('Сделка предназначена не для H2H API, а для Merchant API.');
        }

        Gate::authorize('access-to-order', $order);

        if ($order->status->notEquals(OrderStatus::PENDING)) {
            return response()->failWithMessage('It is not possible to finish a completed order.');
        }
        if ($order->dispute) {
            return response()->failWithMessage('Unable to finish an order in dispute.');
        }

        try {
            services()->order()->finishOrderAsSuccessful($order->id, OrderSubStatus::CANCELED);

            $order->refresh();

            $order->load('dispute', 'paymentGateway', 'paymentDetail');

            return response()->success(
                OrderResource::make($order)
            );
        } catch (OrderException $e) {
            return response()->failWithMessage($e->getMessage());
        }
    }

    public function cancel(Order $order): JsonResponse
    {
        if (! $order->is_h2h) {
            return response()->failWithMessage('Сделка предназначена не для H2H API, а для Merchant API.');
        }

        Gate::authorize('access-to-order', $order);

        if ($order->status->notEquals(OrderStatus::PENDING)) {
            return response()->failWithMessage('It is not possible to cancel a completed order.');
        }
        if ($order->dispute) {
            return response()->failWithMessage('Unable to cancel an order in dispute.');
        }

        try {
            services()->order()->finishOrderAsFailed($order->id, OrderSubStatus::CANCELED);

            $order->refresh();

            $order->load('dispute', 'paymentGateway', 'paymentDetail');

            return response()->success(
                OrderResource::make($order)
            );
        } catch (OrderException $e) {
            return response()->failWithMessage($e->getMessage());
        }
    }
}
