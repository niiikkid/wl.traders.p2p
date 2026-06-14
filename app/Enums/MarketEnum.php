<?php

namespace App\Enums;

use App\Traits\Enumable;

enum MarketEnum: string
{
    use Enumable;

    case BYBIT = 'bybit';
    case BINANCE = 'binance';
    case RAPIRA = 'rapira';
    case MANUAL = 'manual';
    case MERCHANT_API = 'merchant_api';

    public function isDeprecated(): bool
    {
        return $this === self::RAPIRA;
    }

    /**
     * @return MarketEnum[]
     */
    public static function selectableCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $case) => ! $case->isDeprecated()
        ));
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
        return match ($this) {
            self::RAPIRA => self::BYBIT,
            default => $this,
        };
    }
}
