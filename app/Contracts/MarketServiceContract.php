<?php

namespace App\Contracts;

use App\Enums\MarketEnum;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Support\Collection;

interface MarketServiceContract
{
    public function loadAllPrices(): void;

    public function loadPricesFor(Currency $currency, MarketEnum $market): void;

    public function getSellPrice(Currency $currency, MarketEnum $market, bool $withoutFalling = true): Money;

    public function getBuyPrice(Currency $currency, MarketEnum $market, bool $withoutFalling = true): Money;

    public function loadFilterConditions(): void;

    public function getFilterConditions(Currency $currency, MarketEnum $market): array;

    public function getSupportedCurrencies(MarketEnum $market): Collection;
}
