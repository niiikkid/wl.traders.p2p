<?php

namespace App\Contracts;

use App\Enums\MarketEnum;
use App\Models\ValueObjects\Settings\BinancePriceParserSettings;
use App\Models\ValueObjects\Settings\CurrencyPriceParserSettings;
use App\Models\ValueObjects\Settings\PrimeTimeSettings;
use App\Services\Money\Currency;

interface SettingsServiceContract
{
    public function getPrimeTimeBonus(): PrimeTimeSettings;

    public function updatePrimeTimeBonus(string $starts, string $ends, float $rate): void;

    public function getTempVipRequiredDeals(): int;

    public function updateTempVipRequiredDeals(int $value): void;

    public function getTempVipDurationMinutes(): int;

    public function updateTempVipDurationMinutes(int $value): void;

    public function isTempVipEnabled(): bool;

    public function updateTempVipEnabled(bool $enabled): void;

    public function getMarketPriceParser(
        Currency $currency,
        MarketEnum $market
    ): CurrencyPriceParserSettings|BinancePriceParserSettings;

    public function updateMarketPriceParser(
        Currency $currency,
        MarketEnum $market,
        CurrencyPriceParserSettings|BinancePriceParserSettings $settings
    ): void;

    public function getSupportLink(): ?string;

    public function getFundsOnHoldTime(): int;

    public function updateFundsOnHoldTime(int $minutes);

    public function getMaxPendingDisputes(): int;

    public function updateMaxPendingDisputes(int $value): void;

    public function getMaxRejectedDisputes(): array;

    public function updateMaxRejectedDisputes(int $count, int $period): void;

    public function updateSupportLink(string $link): void;

    public function getDefaultReserveBalanceLimit(): int;

    public function updateDefaultReserveBalanceLimit(int $value): void;

    public function getPayoutCurrencySettings(): array;

    public function getPayoutSettingsForCurrency(Currency $currency): array;

    public function updatePayoutCurrencySettings(array $settings): void;

    public function createAll(): void;
}
