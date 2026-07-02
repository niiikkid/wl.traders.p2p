<?php

namespace App\Services\Market;

use App\Contracts\MarketServiceContract;
use App\Enums\MarketEnum;
use App\Jobs\LoadConversionPricesJob;
use App\Jobs\RefreshRateSourceJob;
use App\Models\Merchant;
use App\Models\RateSource;
use App\Models\ValueObjects\Settings\ManualPriceParserSettings;
use App\Services\Market\Utils\MarketStore;
use App\Services\Market\Utils\Parser\BinanceParser;
use App\Services\Market\Utils\Parser\ByBitParser;
use App\Services\Market\Utils\Parser\Parser;
use App\Services\Market\Value\ResolvedMarketPrice;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Services\Rates\RateRefreshService;
use App\Services\Rates\RateSourceStore;
use App\Services\Rates\ResolvedRateBinding;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Collection;
use Throwable;

class MarketService implements MarketServiceContract
{
    protected Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser;
    }

    public function loadAllPrices(): void
    {
        foreach (MarketEnum::cases() as $market) {
            if ($market->isDeprecated() || $market->equals(MarketEnum::MERCHANT_API)) {
                continue;
            }

            $this->supportedCurrenciesForMarket($market)
                ->each(fn (Currency $currency) => LoadConversionPricesJob::dispatch($currency, $market));
        }
    }

    public function resolveRateBinding(Merchant $merchant, Currency $currency): ResolvedRateBinding
    {
        $binding = $merchant->getRateSourceBinding($currency);

        if ($binding) {
            $mode = $binding['mode'] ?? null;

            if ($mode === ResolvedRateBinding::MODE_MERCHANT_API) {
                return ResolvedRateBinding::merchantApi();
            }

            if ($mode === ResolvedRateBinding::MODE_SOURCE && ! empty($binding['source_id'])) {
                $source = RateSource::query()
                    ->active()
                    ->whereKey((int) $binding['source_id'])
                    ->where('quote_currency', $currency->getCode())
                    ->first();

                // A configured-but-missing/inactive source resolves to source mode with no model,
                // so the caller fails clearly instead of silently switching sources.
                return new ResolvedRateBinding(ResolvedRateBinding::MODE_SOURCE, source: $source);
            }
        }

        // Legacy fallback: merchant is still bound via settings.geos (currency => market).
        $market = $merchant->getGeoMarket($currency);

        if ($market && $market->equals(MarketEnum::MERCHANT_API)) {
            return ResolvedRateBinding::merchantApi();
        }

        if ($market) {
            return ResolvedRateBinding::legacyMarket($market);
        }

        return new ResolvedRateBinding(ResolvedRateBinding::MODE_LEGACY_MARKET, market: null);
    }

    public function getSourceRate(RateSource $source): Money
    {
        $cached = RateSourceStore::get($source);

        if ($cached && $cached->greaterThanZero()) {
            return $cached;
        }

        $source->refresh();

        if ($source->rate instanceof Money && $source->rate->greaterThanZero()) {
            RateSourceStore::put($source, $source->rate);

            return $source->rate;
        }

        return new Money(0, $source->quoteCurrency());
    }

    public function refreshSource(RateSource $source): void
    {
        (new RateRefreshService)->refresh($source);
    }

    public function previewSource(RateSource $source): array
    {
        return (new RateRefreshService)->preview($source);
    }

    public function refreshAllActiveSources(): void
    {
        RateSource::query()
            ->active()
            ->automatic()
            ->pluck('id')
            ->each(fn (int $id) => RefreshRateSourceJob::dispatch($id));
    }

    public function loadPricesFor(Currency $currency, MarketEnum $market = MarketEnum::BYBIT): void
    {
        if ($market->isDeprecated()) {
            return;
        }

        if (! $this->supportsCurrency($market, $currency)) {
            return;
        }

        try {
            if ($market->equals(MarketEnum::MERCHANT_API)) {
                return;
            }

            if ($market->equals(MarketEnum::MANUAL)) {
                $settings = services()->settings()->getMarketPriceParser($currency, $market);

                if (! $settings instanceof ManualPriceParserSettings) {
                    return;
                }

                MarketStore::putPrice(
                    currency: $currency,
                    market: $market,
                    buy_price: Money::fromPrecision((string) $settings->buy->rate, $currency)->toUnits(),
                    sell_price: Money::fromPrecision((string) $settings->sell->rate, $currency)->toUnits()
                );

                return;
            }

            $prices = $this->parser->getPrices($currency, $market);

            MarketStore::putPrice(
                currency: $currency,
                market: $market,
                buy_price: $prices->buyPrice->toUnits(),
                sell_price: $prices->sellPrice->toUnits()
            );
        } catch (Throwable $e) {
            if (! $this->shouldSkipReporting($e)) {
                report($e);
            }
        }
    }

    public function getSellPrice(Currency $currency, MarketEnum $market = MarketEnum::BYBIT, bool $withoutFalling = true): Money
    {
        return $this->getResolvedSellPrice($currency, $market, $withoutFalling)->price;
    }

    public function getResolvedSellPrice(Currency $currency, MarketEnum $market = MarketEnum::BYBIT, bool $withoutFalling = true): ResolvedMarketPrice
    {
        return $this->resolvePrice(
            currency: $currency,
            market: $market,
            withoutFalling: $withoutFalling,
            getter: fn (Currency $currency, MarketEnum $market) => MarketStore::getSellPrice($currency, $market)
        );
    }

    public function getBuyPrice(Currency $currency, MarketEnum $market = MarketEnum::BYBIT, bool $withoutFalling = true): Money
    {
        return $this->getResolvedBuyPrice($currency, $market, $withoutFalling)->price;
    }

    public function getResolvedBuyPrice(Currency $currency, MarketEnum $market = MarketEnum::BYBIT, bool $withoutFalling = true): ResolvedMarketPrice
    {
        return $this->resolvePrice(
            currency: $currency,
            market: $market,
            withoutFalling: $withoutFalling,
            getter: fn (Currency $currency, MarketEnum $market) => MarketStore::getBuyPrice($currency, $market)
        );
    }

    public function loadFilterConditions(): void
    {
        foreach (MarketEnum::cases() as $market) {
            if ($market->isDeprecated()) {
                continue;
            }

            try {
                if ($market->equals(MarketEnum::BYBIT)) {
                    $methods = (new ByBitParser)->parsePaymentMethodsList();
                    MarketStore::putFilterConditions(market: $market, conditions: $methods);

                    continue;
                }

                if ($market->equals(MarketEnum::BINANCE)) {
                    $this->supportedCurrenciesForMarket($market)
                        ->each(function (Currency $currency) use ($market) {
                            try {
                                $conditions = (new BinanceParser)->parseFilterConditions($currency);
                                MarketStore::putFilterConditions(
                                    market: $market,
                                    conditions: $conditions,
                                    currency: $currency
                                );
                            } catch (Throwable $e) {
                                if (! $this->shouldSkipReporting($e)) {
                                    report($e);
                                }
                            }
                        });
                }
            } catch (Throwable $e) {
                if (! $this->shouldSkipReporting($e)) {
                    report($e);
                }
            }
        }
    }

    public function getFilterConditions(Currency $currency, MarketEnum $market): array
    {
        if ($market->equals(MarketEnum::BYBIT)) {
            return $this->getBybitFilterConditions($currency);
        }

        if ($market->equals(MarketEnum::BINANCE)) {
            return MarketStore::getFilterConditions($market, $currency) ?? [];
        }

        return [];
    }

    public function getSupportedCurrencies(MarketEnum $market): Collection
    {
        return $this->supportedCurrenciesForMarket($market);
    }

    protected function supportsCurrency(MarketEnum $market, Currency $currency): bool
    {
        return $this->supportedCurrenciesForMarket($market)
            ->contains(fn (Currency $supported) => $supported->getCode() === $currency->getCode());
    }

    protected function supportedCurrenciesForMarket(MarketEnum $market): Collection
    {
        $rubCode = Currency::RUB()->getCode();

        return match ($market) {
            MarketEnum::BYBIT => Currency::getAll()->values(),
            MarketEnum::BINANCE => Currency::getAll()
                ->filter(fn (Currency $currency) => $currency->getCode() !== $rubCode)
                ->values(),
            MarketEnum::MANUAL => Currency::getAll()->values(),
            MarketEnum::MERCHANT_API => Currency::getAll()->values(),
            default => collect(),
        };
    }

    protected function getBybitFilterConditions(Currency $currency): array
    {
        $paymentList = MarketStore::getFilterConditions(MarketEnum::BYBIT) ?? [];

        if (empty($paymentList)) {
            return [];
        }

        $currencyPaymentIdMap = json_decode($paymentList['currencyPaymentIdMap'] ?? '{}', true);
        $paymentConfigVo = $paymentList['paymentConfigVo'] ?? [];
        $currencyPaymentIdMapForCurrentCurrency = $currencyPaymentIdMap[strtoupper($currency->getCode())] ?? [];

        $methods = [];

        foreach ($paymentConfigVo as $paymentMethod) {
            if (in_array($paymentMethod['paymentType'] ?? null, $currencyPaymentIdMapForCurrentCurrency)) {
                $methods[] = [
                    'id' => $paymentMethod['paymentType'],
                    'name' => $paymentMethod['paymentName'],
                ];
            }
        }

        return [
            'payment_methods' => $methods,
        ];
    }

    protected function resolvePrice(
        Currency $currency,
        MarketEnum $market,
        bool $withoutFalling,
        callable $getter
    ): ResolvedMarketPrice {
        $resolvedMarket = $market;
        $price = $getter($currency, $market);

        if (! $price && $withoutFalling) {
            foreach ($this->fallbackMarkets($market, $currency) as $fallbackMarket) {
                $price = $getter($currency, $fallbackMarket);
                if ($price) {
                    $resolvedMarket = $fallbackMarket;
                    break;
                }
            }
        }

        if (! $price) {
            $price = 0;
        }

        return new ResolvedMarketPrice(
            price: new Money($price, $currency),
            market: $resolvedMarket,
        );
    }

    /**
     * @return MarketEnum[]
     */
    protected function fallbackMarkets(MarketEnum $current, Currency $currency): array
    {
        return array_values(array_filter(
            MarketEnum::cases(),
            function (MarketEnum $market) use ($current, $currency) {
                if ($market->equals($current) || $market->isDeprecated()) {
                    return false;
                }

                return $this->supportsCurrency($market, $currency);
            }
        ));
    }

    protected function shouldSkipReporting(Throwable $exception): bool
    {
        $current = $exception;

        while ($current instanceof Throwable) {
            if ($current instanceof ConnectException) {
                $message = $current->getMessage();

                if (
                    str_contains($message, 'cURL error 35')
                    || str_contains($message, 'cURL error 28')
                ) {
                    return true;
                }
            }

            if (
                $current instanceof \ErrorException
                && str_contains($current->getMessage(), 'Trying to access array offset on null')
            ) {
                return true;
            }

            $current = $current->getPrevious();
        }

        return false;
    }
}
