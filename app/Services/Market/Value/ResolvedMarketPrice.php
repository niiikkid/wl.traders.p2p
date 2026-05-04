<?php

namespace App\Services\Market\Value;

use App\Enums\MarketEnum;
use App\Services\Money\Money;

class ResolvedMarketPrice
{
    public function __construct(
        public Money $price,
        public MarketEnum $market,
    ) {}
}
