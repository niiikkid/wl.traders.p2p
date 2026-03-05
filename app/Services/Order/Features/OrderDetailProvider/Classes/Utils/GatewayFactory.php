<?php

namespace App\Services\Order\Features\OrderDetailProvider\Classes\Utils;

use App\Models\Merchant;
use App\Models\PaymentGateway;
use App\Services\Order\Features\OrderDetailProvider\Values\Gateway;

class GatewayFactory
{
    public function __construct(
        protected Merchant $merchant
    ) {}

    public function make(PaymentGateway $paymentGateway): Gateway
    {
        $customGatewaySettings = $this->merchant->gateway_settings[$paymentGateway->id] ?? null;

        $serviceCommissionRateTotal = $paymentGateway->total_service_commission_rate_for_orders;

        if (isset($customGatewaySettings['custom_gateway_commission']) && $customGatewaySettings['custom_gateway_commission'] > 0) {
            $serviceCommissionRateTotal = $customGatewaySettings['custom_gateway_commission'];
        } elseif (isset($customGatewaySettings['custom_gateway_commission']) && (int)$customGatewaySettings['custom_gateway_commission'] === 0) {
            $serviceCommissionRateTotal = 0;
        }

        if (!empty($customGatewaySettings['custom_gateway_reservation_time'])) {
            $reservationTime = (int)$customGatewaySettings['custom_gateway_reservation_time'];
        } else {
            $reservationTime = $paymentGateway->reservation_time_for_orders;
        }

        return new Gateway(
            id: $paymentGateway->id,
            code: $paymentGateway->code,
            reservationTime: $reservationTime,
            serviceCommissionRate: $serviceCommissionRateTotal,
            traderCommissionRate: $paymentGateway->trader_commission_rate_for_orders,
        );
    }
}
