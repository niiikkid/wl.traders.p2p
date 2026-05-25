<?php

namespace App\Http\Controllers\API\H2H;

use App\Enums\OrderStatus;
use App\Exceptions\DisputeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\H2H\Dispute\StoreRequest;
use App\Http\Resources\API\H2H\DisputeResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DisputeController extends Controller
{
    public function show(Order $order): JsonResponse
    {
        if (! $order->is_h2h) {
            return response()->failWithMessage('Сделка предназначена не для H2H API, а для Merchant API.');
        }
        if (! $order->dispute) {
            return response()->failWithMessage('По сделке пока что небыло споров.');
        }

        Gate::authorize('access-to-order', $order);

        return response()->success(
            DisputeResource::make($order->dispute)
        );
    }

    public function store(StoreRequest $request, Order $order): JsonResponse
    {
        if (! $order->is_h2h) {
            return response()->failWithMessage('Сделка предназначена не для H2H API, а для Merchant API.');
        }

        Gate::authorize('access-to-order', $order);

        if ($order->status->equals(OrderStatus::SUCCESS)) {
            return response()->failWithMessage(
                "По сделке нельзя открыть спор.\nСделка успешно завершена.\nUUID сделки: {$order->uuid}",
            );
        }

        if ($order->status->equals(OrderStatus::PENDING)) {
            return response()->failWithMessage(
                "По сделке нельзя открыть спор.\nСделка ещё обрабатывается.\nUUID сделки: {$order->uuid}",
            );
        }

        try {
            $dispute = services()->dispute()->create($order->id, $request->receipt);

            return response()->success(
                DisputeResource::make($dispute)
            );
        } catch (DisputeException $e) {
            return response()->failWithMessage($e->getMessage());
        }
    }
}
