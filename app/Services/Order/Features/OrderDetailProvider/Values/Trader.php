<?php

namespace App\Services\Order\Features\OrderDetailProvider\Values;

use App\Services\Money\Money;

class Trader
{
    public function __construct(
        public int $id,
        public Money $trustBalance,
        public ?int $teamLeaderID,
        public float $teamLeaderCommissionRate,
        public float $teamLeaderSplitFromServicePercent,
    )
    {}
}
