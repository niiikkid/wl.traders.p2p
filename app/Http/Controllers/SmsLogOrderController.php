<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\OrderSmsLogException;
use App\Http\Requests\SmsLog\LinkOrderRequest;
use App\Http\Resources\SmsLogResource;
use App\Http\Resources\UnlinkedOrderForSmsLogResource;
use App\Models\Order;
use App\Models\SmsLog;
use App\Services\Sms\OrderSmsLogLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

class SmsLogOrderController extends Controller
{
    public function index(Request $request, SmsLog $smsLog, OrderSmsLogLinkService $orderSmsLogLinkService)
    {
        Gate::authorize('access-to-sms-log', $smsLog);

        if ($smsLog->order_id !== null) {
            return response()->failWithMessage('К сообщению уже привязана сделка.', 422);
        }

        if (! SmsLog::query()->whereKey($smsLog->id)->unlinked()->notRejected()->linkableToOrder()->exists()) {
            return response()->failWithMessage('Сообщение недоступно для привязки.', 422);
        }

        $orders = $orderSmsLogLinkService->paginateUnlinkedOrdersForSmsLog(
            $smsLog,
            $request->string('amount')->toString() ?: null,
            $request->string('payment_detail')->toString() ?: null,
            (int) ($request->integer('per_page') ?: 10),
        );

        $request->attributes->set('sms_log_for_unlinked_orders', $smsLog);

        return response()->success(
            UnlinkedOrderForSmsLogResource::collection($orders)
        );
    }

    public function store(LinkOrderRequest $request, SmsLog $smsLog, OrderSmsLogLinkService $orderSmsLogLinkService)
    {
        Gate::authorize('access-to-sms-log', $smsLog);

        if ($smsLog->order_id !== null) {
            return response()->failWithMessage('К сообщению уже привязана сделка.', 422);
        }

        if (! SmsLog::query()->whereKey($smsLog->id)->unlinked()->notRejected()->linkableToOrder()->exists()) {
            return response()->failWithMessage('Сообщение недоступно для привязки.', 422);
        }

        $order = Order::query()->findOrFail((int) $request->validated('order_id'));
        Gate::authorize('access-to-order', $order);

        try {
            $linkedSmsLog = $orderSmsLogLinkService->link($order, $smsLog->id);
        } catch (OrderSmsLogException $exception) {
            return response()->failWithMessage($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->failWithMessage('Не удалось привязать сделку.', 500);
        }

        $linkedSmsLog->load([
            'order.paymentDetail',
            'order.paymentGateway',
        ]);

        return response()->success([
            'sms_log' => SmsLogResource::make($linkedSmsLog)->resolve(),
        ]);
    }
}
