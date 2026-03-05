<?php

namespace App\Services\Order\Features\OrderDetailProvider\Values;

use App\Services\Money\Currency;
use App\Services\Money\Money;

class Detail
{
    public function __construct(
        public int      $id,
        public int      $userID,
        public int      $paymentGatewayID,
        public ?int     $userDeviceID,
        public Money    $dailyLimit,
        public Money    $currentDailyLimit,
        public Currency $currency,
        public Money    $exchangePrice,
        public Money    $totalProfit,
        public Money    $serviceProfit,
        public Money    $merchantProfit,
        public Money    $traderProfit,
        public Money    $teamLeaderProfit,
        public float    $traderCommissionRate,
        public float    $teamLeaderCommissionRate,
        public Money    $traderPaidForOrder,
        public Gateway  $gateway,
        public Trader   $trader,
        public Money    $amount,
    )
    {}
}
