<?php

namespace App\Services\Market;

use App\Contracts\MarketServiceContract;
use App\Enums\MarketEnum;
use App\Jobs\LoadConversionPricesJob;
use App\Services\Market\Utils\Parser\BinanceParser;
use App\Services\Market\Utils\Parser\ByBitParser;
use App\Services\Money\Currency;
use App\Services\Market\Utils\MarketStore;
use App\Services\Market\Utils\Parser\Parser;
use App\Services\Money\Money;
use Illuminate\Support\Collection;
use Throwable;

class MarketService implements MarketServiceContract
{
    protected Parser  $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    public function loadAllPrices(): void
    {
        foreach (MarketEnum::cases() as $market) {
            $this->supportedCurrenciesForMarket($market)
                ->each(fn (Currency $currency) => LoadConversionPricesJob::dispatch($currency, $market));
        }
    }

    public function loadPricesFor(Currency $currency, MarketEnum $market = MarketEnum::BYBIT): void
    {
        if (! $this->supportsCurrency($market, $currency)) {
            return;
        }

        try {
            $prices = $this->parser->getPrices($currency, $market);

            MarketStore::putPrice(
                currency: $currency,
                market: $market,
                buy_price: $prices->buyPrice->toUnits(),
                sell_price: $prices->sellPrice->toUnits()
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function getSellPrice(Currency $currency, MarketEnum $market = MarketEnum::BYBIT, bool $withoutFalling = true): Money
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
            try {
                if ($market->equals(MarketEnum::BYBIT)) {
                    $methods = (new ByBitParser())->parsePaymentMethodsList();
                    MarketStore::putFilterConditions(market: $market, conditions: $methods);
                    continue;
                }

                if ($market->equals(MarketEnum::BINANCE)) {
                    $this->supportedCurrenciesForMarket($market)
                        ->each(function (Currency $currency) use ($market) {
                            try {
                                $conditions = (new BinanceParser())->parseFilterConditions($currency);
                                MarketStore::putFilterConditions(
                                    market: $market,
                                    conditions: $conditions,
                                    currency: $currency
                                );
                            } catch (Throwable $e) {
                                report($e);
                            }
                        });
                }
            } catch (Throwable $e) {
                report($e);
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
            MarketEnum::RAPIRA => collect([Currency::RUB()]),
            MarketEnum::BYBIT => Currency::getAll()->values(),
            MarketEnum::BINANCE => Currency::getAll()
                ->filter(fn (Currency $currency) => $currency->getCode() !== $rubCode)
                ->values(),
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
    ): Money {
        $price = $getter($currency, $market);

        if (! $price && $withoutFalling) {
            foreach ($this->fallbackMarkets($market, $currency) as $fallbackMarket) {
                $price = $getter($currency, $fallbackMarket);
                if ($price) {
                    break;
                }
            }
        }

        if (! $price) {
            $price = 0;
        }

        return new Money($price, $currency);
    }

    /**
     * @return MarketEnum[]
     */
    protected function fallbackMarkets(MarketEnum $current, Currency $currency): array
    {
        return array_values(array_filter(
            MarketEnum::cases(),
            function (MarketEnum $market) use ($current, $currency) {
                if ($market->equals($current)) {
                    return false;
                }

                return $this->supportsCurrency($market, $currency);
            }
        ));
    }
}
