<?php

namespace App\Services\Market\Utils\Parser;

use App\Enums\MarketEnum;
use App\Services\Market\Value\MarketPrices;
use App\Services\Money\Currency;
use Exception;

class Parser
{
    public function getPrices(Currency $currency, MarketEnum $market): MarketPrices
    {
        return match (true) {
            $market->equals(MarketEnum::RAPIRA) => (new RapiraParser())->getPrices($currency),
            $market->equals(MarketEnum::BYBIT) => (new ByBitParser())->getPrices($currency),
            $market->equals(MarketEnum::BINANCE) => (new BinanceParser())->getPrices($currency),
            default => throw new Exception('Error: Market not found.'),
        };
    }
}
