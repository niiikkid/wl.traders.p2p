<?php

namespace App\Http\Controllers;

use App\DTO\Order\AssignDetailsToOrderDTO;
use App\Exceptions\OrderException;
use App\Http\Requests\PaymentLink\Dispute\StoreRequest;
use App\Models\Order;
use App\Models\PaymentGateway;
use Inertia\Inertia;

class PaymentLinkController extends Controller
{
    public function show(Order $order)
    {
        //Временно отключено
        /*$gatewaySettings = collect($order->merchant->gateway_settings)->filter(function ($setting) {
            return $setting['active'] ?? true;
        });*/

        $availableGateways = PaymentGateway::query()
            //->whereIn('id', $gatewaySettings->keys()->all())В ременно отключено
            ->where(function ($query) use ($order) {
                $query->where('min_limit', '<=', intval($order->amount->toBeauty())); //TODO min_limit as units
                $query->where('max_limit', '>=', intval($order->amount->toBeauty()));
            })
            ->where('currency', $order->currency)
            ->active()
            ->whereRelation('paymentDetails.user', 'is_online', true)
            ->whereRelation('paymentDetails', 'archived_at')
            ->whereRelation('paymentDetails', function ($query) use ($order) {
                $query->whereRaw('(daily_limit - current_daily_limit) >= ?', [$order->amount->toUnitsInt()]);
                $query->where(function ($subQuery) {
                    $subQuery->whereNull('daily_successful_orders_limit')
                        ->orWhereColumn('current_daily_successful_orders_count', '<', 'daily_successful_orders_limit');
                });
            })
            ->get()
            ->transform(function (PaymentGateway $paymentGateway) use ($order) {
                return [
                    'id' => $paymentGateway->id,
                    'name' => $paymentGateway->name,
                    'logo_path' => $paymentGateway->logo ? asset('storage/logos/'.$paymentGateway->logo) : null, //TODO убрать в модель
                ];
            })
            ->toArray();

        $data = [
            'order_status' => $order->status->value,
            'uuid' => $order->uuid,
            'name' => $order->merchant->name,
            'amount' => $order->amount->toBeauty(),
            'amount_formated' => number_format($order->amount->toBeauty(), 0, ',', ''),
            'currency_symbol' => $order->amount->getCurrency()->getSymbol(),
            'support_link' => services()->settings()->getSupportLink(),
            'detail' => $order->paymentDetail?->detail,
            'detail_type' => $order->paymentDetail?->detail_type->value,
            'initials' => $order->paymentDetail?->initials,
            'payment_gateway' => $order->paymentGateway?->name,
            'success_url' => $order->success_url,
            'fail_url' => $order->fail_url,
            'created_at' => $order->created_at->toDateTimeString(),
            'expires_at' => $order->expires_at?->toDateTimeString(),
            'now' => now()->toDateTimeString(),
            'has_dispute' => intval(!! $order->dispute),
            'dispute_status' => $order->dispute?->status->value,
            'dispute_cancel_reason' => $order->dispute?->reason,
            'manually' => !$order->paymentDetail?->detail,
            'gateway_selected' => (bool) $order->paymentDetail,
            'available_gateways' => $availableGateways
        ];

        return Inertia::render('PaymentLink/Index', compact('data'));
    }

    public function storeDispute(StoreRequest $request, Order $order)
    {
        services()->dispute()->create($order->id, $request->receipt);
    }

    public function storePaymentDetail(Order $order, PaymentGateway $paymentGateway)
    {
        if ($order->payment_detail_id) {
            return;
        }

        try {
            retry(5, function () use ($order, $paymentGateway) {
                return services()->order()->assignDetailsToOrder(
                    orderID: $order->id,
                    data: new AssignDetailsToOrderDTO(
                        gateway: $paymentGateway,
                    )
                );
            }, 1000);
        } catch (OrderException $e) {
            report($e);

            return redirect()->back()->with('message', 'Подходящие реквизиты не найдены, пожалуйста попробуйте другой метод.');
        }
    }
}
