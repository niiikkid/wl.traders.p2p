<?php

namespace App\Contracts;

use App\Enums\MarketEnum;
use App\Models\ValueObjects\Settings\BinancePriceParserSettings;
use App\Models\ValueObjects\Settings\CurrencyPriceParserSettings;
use App\Models\ValueObjects\Settings\ManualPriceParserSettings;
use App\Models\ValueObjects\Settings\PrimeTimeSettings;
use App\Services\Money\Currency;

interface SettingsServiceContract
{
    public function getAppSlogan(): string;

    public function updateAppSlogan(string $value): void;

    public function getPrimeTimeBonus(): PrimeTimeSettings;

    public function updatePrimeTimeBonus(string $starts, string $ends, float $rate): void;

    public function getMarketPriceParser(
        Currency $currency,
        MarketEnum $market
    ): CurrencyPriceParserSettings|BinancePriceParserSettings|ManualPriceParserSettings;

    public function updateMarketPriceParser(
        Currency $currency,
        MarketEnum $market,
        CurrencyPriceParserSettings|BinancePriceParserSettings|ManualPriceParserSettings $settings
    ): void;

    public function getFundsOnHoldTime(): int;

    public function updateFundsOnHoldTime(int $minutes);

    public function getMaxPendingDisputes(): int;

    public function updateMaxPendingDisputes(int $value): void;

    public function getMaxRejectedDisputes(): array;

    public function updateMaxRejectedDisputes(int $count, int $period): void;

    public function getDefaultReserveBalanceLimit(): int;

    public function updateDefaultReserveBalanceLimit(int $value): void;

    public function getPayoutCurrencySettings(): array;

    public function getPayoutSettingsForCurrency(Currency $currency): array;

    public function updatePayoutCurrencySettings(array $settings): void;

    /**
     * @return array{type:string,slug:string,name:string,colorScheme:string,tokens:array<string,string>}|null
     */
    public function getPublishedTheme(): ?array;

    /**
     * @param  array{type:string,slug:string,name:string,colorScheme:string,tokens:array<string,string>}|null  $theme
     */
    public function updatePublishedTheme(?array $theme): void;

    public function createAll(): void;
}
