<?php

namespace App\Services\Rates;

use App\Enums\MarketEnum;
use App\Models\RateSource;

/**
 * Result of resolving how a merchant obtains the rate for a given currency+direction.
 *
 * Modes:
 * - `source`: a configured {@see RateSource} is attached (new model);
 * - `merchant_api`: the merchant provides the rate itself (validated against reference/deviation);
 * - `legacy_market`: no new binding yet — fall back to the legacy {@see MarketEnum} + cache path.
 */
class ResolvedRateBinding
{
    public const MODE_SOURCE = 'source';

    public const MODE_MERCHANT_API = 'merchant_api';

    public const MODE_LEGACY_MARKET = 'legacy_market';

    public function __construct(
        public string $mode,
        public ?RateSource $source = null,
        public ?MarketEnum $market = null,
    ) {}

    public static function source(RateSource $source): self
    {
        return new self(self::MODE_SOURCE, source: $source);
    }

    public static function merchantApi(): self
    {
        return new self(self::MODE_MERCHANT_API);
    }

    public static function legacyMarket(MarketEnum $market): self
    {
        return new self(self::MODE_LEGACY_MARKET, market: $market);
    }

    public function isSource(): bool
    {
        return $this->mode === self::MODE_SOURCE;
    }

    public function isMerchantApi(): bool
    {
        return $this->mode === self::MODE_MERCHANT_API;
    }

    public function isLegacyMarket(): bool
    {
        return $this->mode === self::MODE_LEGACY_MARKET;
    }
}
