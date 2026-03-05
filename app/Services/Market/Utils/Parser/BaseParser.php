<?php

namespace App\Services\Market\Utils\Parser;

use App\Services\Market\Value\MarketPrices;
use App\Services\Money\Currency;

abstract class BaseParser
{
    abstract public function getPrices(Currency $currency): MarketPrices;
}
