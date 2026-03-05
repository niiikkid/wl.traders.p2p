<?php

namespace App\Services\Market\Value;

use App\Services\Money\Money;

class MarketPrices
{
    public function __construct(
        public Money $buyPrice,
        public Money $sellPrice,
    )
    {}
}
