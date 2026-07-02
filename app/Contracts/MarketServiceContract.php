<?php

namespace App\Contracts;

use App\Enums\MarketEnum;
use App\Enums\RateSourceDirection;
use App\Models\Merchant;
use App\Models\RateSource;
use App\Services\Market\Value\ResolvedMarketPrice;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Services\Rates\ResolvedRateBinding;
use Illuminate\Support\Collection;

interface MarketServiceContract
{
    public function loadAllPrices(): void;

    /**
     * Resolve how a merchant obtains the rate for a currency+direction
     * (attached rate source, merchant-provided rate, or legacy market fallback).
     */
    public function resolveRateBinding(Merchant $merchant, Currency $currency, RateSourceDirection $direction): ResolvedRateBinding;

    /**
     * Return the ready rate for a configured rate source (cache first, DB fallback).
     */
    public function getSourceRate(RateSource $source): Money;

    /**
     * Refresh a single rate source from its provider (called from the queue).
     */
    public function refreshSource(RateSource $source): void;

    /**
     * Parse a rate for a (possibly unsaved) source without persisting — used for admin preview.
     *
     * @return array{status: string, side: string, rate: string|null, error: string|null}
     */
    public function previewSource(RateSource $source): array;

    /**
     * Queue a refresh for every active automatic rate source.
     */
    public function refreshAllActiveSources(): void;

    public function loadPricesFor(Currency $currency, MarketEnum $market): void;

    public function getSellPrice(Currency $currency, MarketEnum $market, bool $withoutFalling = true): Money;

    public function getBuyPrice(Currency $currency, MarketEnum $market, bool $withoutFalling = true): Money;

    public function getResolvedSellPrice(Currency $currency, MarketEnum $market, bool $withoutFalling = true): ResolvedMarketPrice;

    public function getResolvedBuyPrice(Currency $currency, MarketEnum $market, bool $withoutFalling = true): ResolvedMarketPrice;

    public function loadFilterConditions(): void;

    public function getFilterConditions(Currency $currency, MarketEnum $market): array;

    public function getSupportedCurrencies(MarketEnum $market): Collection;
}
