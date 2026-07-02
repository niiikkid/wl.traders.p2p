<?php

namespace App\Enums;

use App\Traits\Enumable;

enum MarketEnum: string
{
    use Enumable;

    case BYBIT = 'bybit';
    case BINANCE = 'binance';
    case MANUAL = 'manual';
    case MERCHANT_API = 'merchant_api';

    /**
     * Kept for backward compatibility with callers; no market is deprecated anymore.
     */
    public function isDeprecated(): bool
    {
        return false;
    }

    /**
     * @return MarketEnum[]
     */
    public static function selectableCases(): array
    {
        return self::cases();
    }

    /**
     * @return string[]
     */
    public static function selectableValues(): array
    {
        return array_map(fn (self $case) => $case->value, self::selectableCases());
    }

    public function activeReplacement(): self
    {
        return $this;
    }
}
