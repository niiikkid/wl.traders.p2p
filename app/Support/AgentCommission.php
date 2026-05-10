<?php

namespace App\Support;

use App\Services\Money\Currency;
use App\Services\Money\Money;

class AgentCommission
{
    public const DEFAULT_RATE = 0.2;

    public static function calculate(Money $turnover, Money $serviceProfit): Money
    {
        $commission = $turnover->mul((string) (self::DEFAULT_RATE / 100));

        if ($commission->greaterThan($serviceProfit)) {
            return $serviceProfit;
        }

        return $commission;
    }

    public static function zero(): Money
    {
        return Money::fromUnits(0, Currency::USDT());
    }
}
