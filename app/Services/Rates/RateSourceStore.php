<?php

namespace App\Services\Rates;

use App\Models\RateSource;
use App\Services\Money\Money;

/**
 * Fast cache layer for a rate source's ready rate. The durable source of truth
 * is `rate_sources.rate`; this cache only accelerates the read path.
 */
class RateSourceStore
{
    protected static function cacheKey(int $rateSourceId): string
    {
        return 'rate-source.'.$rateSourceId.'.rate';
    }

    public static function put(RateSource $source, Money $rate): void
    {
        $ttl = is_local() ? 60 * 60 * 24 * 365 : 60 * 30;

        cache()->put(self::cacheKey($source->id), [
            'rate' => $rate->toUnits(),
            'currency' => $rate->getCurrency()->getCode(),
        ], $ttl);
    }

    public static function get(RateSource $source): ?Money
    {
        $payload = cache()->get(self::cacheKey($source->id));

        if (empty($payload) || empty($payload['rate']) || empty($payload['currency'])) {
            return null;
        }

        return Money::fromUnits($payload['rate'], $payload['currency']);
    }

    public static function forget(RateSource $source): void
    {
        cache()->forget(self::cacheKey($source->id));
    }
}
