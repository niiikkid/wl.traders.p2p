<?php

namespace App\Services\Order\Features\OrderDetailProvider\Classes\Utils;

use App\Models\Merchant;
use App\Models\PaymentGateway;
use App\Services\Money\Money;
use App\Services\Order\Features\OrderDetailProvider\Values\Gateway;

class GatewayFactory
{
    public function __construct(
        protected Merchant $merchant,
        protected ?Money $amount = null
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

        $traderCommissionRate = (float) $paymentGateway->trader_commission_rate_for_orders;

        if ($this->amount && $paymentGateway->use_flexible_trader_commission_for_orders) {
            $traderCommissionRate = $paymentGateway->resolveTraderCommissionRateForOrderAmount(
                (float) intval($this->amount->toBeauty())
            );

            if (! isset($customGatewaySettings['custom_gateway_commission'])) {
                $serviceCommissionRateTotal = $paymentGateway->resolveTotalServiceCommissionRateForOrderAmount(
                    (float) intval($this->amount->toBeauty())
                );
            }
        }

        return new Gateway(
            id: $paymentGateway->id,
            code: $paymentGateway->code,
            reservationTime: $reservationTime,
            serviceCommissionRate: $serviceCommissionRateTotal,
            traderCommissionRate: $traderCommissionRate,
        );
    }
}
