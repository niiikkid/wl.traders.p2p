<?php

namespace App\Enums;

use App\Traits\Enumable;

enum RateSourceType: string
{
    use Enumable;

    case MANUAL = 'manual';
    case BYBIT = 'bybit';
    case BINANCE = 'binance';

    /**
     * Automatic sources are refreshed by external P2P parsers on a schedule.
     * Manual sources hold an admin-provided rate and are not parsed.
     */
    public function isAutomatic(): bool
    {
        return match ($this) {
            self::BYBIT, self::BINANCE => true,
            self::MANUAL => false,
        };
    }

    /**
     * Map a source type to the legacy {@see MarketEnum} value stored on
     * orders/payouts so the public API contract (`market`, `rate.market`) stays stable.
     */
    public function toMarketEnum(): MarketEnum
    {
        return match ($this) {
            self::MANUAL => MarketEnum::MANUAL,
            self::BYBIT => MarketEnum::BYBIT,
            self::BINANCE => MarketEnum::BINANCE,
        };
    }
}
