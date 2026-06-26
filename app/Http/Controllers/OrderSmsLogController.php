<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\OrderSmsLogException;
use App\Http\Requests\Order\LinkSmsLogRequest;
use App\Http\Resources\UnlinkedSmsLogResource;
use App\Models\Order;
use App\Services\Sms\OrderSmsLogLinkService;
use Illuminate\Support\Facades\Gate;
use Throwable;

class OrderSmsLogController extends Controller
{
    public function index(Order $order, OrderSmsLogLinkService $orderSmsLogLinkService)
    {
        Gate::authorize('access-to-order', $order);

        $smsLogs = $orderSmsLogLinkService->unlinkedIncomingForOrder($order);

        request()->attributes->set('order_for_unlinked_sms_logs', $order);

        return response()->success([
            'sms_logs' => UnlinkedSmsLogResource::collection($smsLogs),
        ]);
    }

    public function store(LinkSmsLogRequest $request, Order $order, OrderSmsLogLinkService $orderSmsLogLinkService)
    {
        Gate::authorize('access-to-order', $order);

        try {
            $smsLog = $orderSmsLogLinkService->link($order, (int) $request->validated('sms_log_id'));
        } catch (OrderSmsLogException $exception) {
            return response()->failWithMessage($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->failWithMessage('Не удалось привязать сообщение.', 500);
        }

        return response()->success([
            'sms_log' => [
                'sender' => $smsLog->sender,
                'message' => $smsLog->message,
                'created_at' => $smsLog->created_at->toISOString(),
            ],
        ]);
    }
}
