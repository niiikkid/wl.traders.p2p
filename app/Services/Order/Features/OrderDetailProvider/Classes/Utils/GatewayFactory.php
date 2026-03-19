<?php

namespace App\Services\Order\Features\OrderDetailProvider\Classes\Utils;

use App\Enums\DetailType;
use App\Models\Merchant;
use App\Models\PaymentGateway;
use App\Support\TraderCommissionTierResolver;
use App\Services\Money\Money;
use App\Services\Order\Features\OrderDetailProvider\Values\Gateway;

class GatewayFactory
{
    public function __construct(
        protected Merchant $merchant,
        protected ?Money $amount = null,
        protected ?DetailType $detailType = null,
    ) {}

    public function make(PaymentGateway $paymentGateway): Gateway
    {
        $serviceCommissionRateTotal = (float) $paymentGateway->total_service_commission_rate_for_orders;
        $traderCommissionRate = (float) $paymentGateway->trader_commission_rate_for_orders;
        $hasMerchantCommissionOverride = false;

        if ($this->detailType) {
            $merchantCommissionSettings = $this->merchant->getCommissionSettingForPair(
                currency: $paymentGateway->currency,
                detailType: $this->detailType
            );

            if ($merchantCommissionSettings) {
                $hasMerchantCommissionOverride = true;
                $traderCommissionRate = (float) ($merchantCommissionSettings['trader_commission_rate_for_orders'] ?? 0);
                $serviceCommissionRateTotal = (float) ($merchantCommissionSettings['total_service_commission_rate_for_orders'] ?? 0);

                if ($this->amount && ($merchantCommissionSettings['use_flexible_trader_commission_for_orders'] ?? false)) {
                    $orderAmount = (float) intval($this->amount->toBeauty());
                    $traderCommissionRate = TraderCommissionTierResolver::resolveRate(
                        tiers: $merchantCommissionSettings['trader_commission_tiers_for_orders'] ?? [],
                        amount: $orderAmount,
                        defaultRate: $traderCommissionRate
                    );
                    $serviceCommissionRateTotal = TraderCommissionTierResolver::resolveRate(
                        tiers: $merchantCommissionSettings['total_service_commission_tiers_for_orders'] ?? [],
                        amount: $orderAmount,
                        defaultRate: $serviceCommissionRateTotal
                    );
                }
            }
        }

        $reservationTime = $paymentGateway->reservation_time_for_orders;

        if (! $hasMerchantCommissionOverride && $this->amount && $paymentGateway->use_flexible_trader_commission_for_orders) {
            $traderCommissionRate = $paymentGateway->resolveTraderCommissionRateForOrderAmount(
                (float) intval($this->amount->toBeauty())
            );

            $serviceCommissionRateTotal = $paymentGateway->resolveTotalServiceCommissionRateForOrderAmount(
                (float) intval($this->amount->toBeauty())
            );
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
