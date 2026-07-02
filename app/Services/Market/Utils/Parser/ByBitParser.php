<?php

namespace App\Services\Market\Utils\Parser;

use App\Enums\MarketEnum;
use App\Models\ValueObjects\Settings\CurrencyPriceParserSideSettings;
use App\Services\Market\Value\MarketPrices;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ByBitParser extends BaseParser
{
    public function getPrices(Currency $currency): MarketPrices
    {
        return new MarketPrices(
            $this->parseBuyPrice($currency),
            $this->parseSellPrice($currency),
        );
    }

    /**
     * Parse a single ready rate for a configured rate source (one side only).
     *
     * @param  string  $p2pSide  'buy' or 'sell' (Bybit side flag 1 or 0)
     */
    public function parseSourceRate(Currency $currency, CurrencyPriceParserSideSettings $side, string $p2pSide): Money
    {
        $paymentMethods = array_values(array_map(
            fn ($value) => strval($value),
            $side->payment_methods ?: []
        ));

        $items = $this->fetchOnlineItems(
            currency: $currency,
            paymentMethods: $paymentMethods,
            amount: $side->amount,
            sideFlag: $p2pSide === 'sell' ? '0' : '1',
        );

        $average = $this->averagePrice(
            items: $items,
            minRecentOrders: $side->min_recent_orders,
            adQuantity: $side->ad_quantity ?: 200,
        );

        return Money::fromPrecision((string) $average, $currency);
    }

    /**
     * @param  array<int, string>  $paymentMethods
     * @return array<int, array<string, mixed>>
     */
    protected function fetchOnlineItems(Currency $currency, array $paymentMethods, ?int $amount, string $sideFlag): array
    {
        $data = [
            'userId' => '',
            'tokenId' => 'USDT',
            'currencyId' => strtoupper($currency->getCode()),
            'payment' => $paymentMethods,
            'side' => $sideFlag,
            'size' => '200',
            'page' => '1',
            'amount' => $amount ? strval($amount) : '',
            'vaMaker' => true,
            'bulkMaker' => false,
            'canTrade' => true,
            'verificationFilter' => 0,
            'sortType' => 'TRADE_PRICE',
            'paymentPeriod' => [],
            'itemRegion' => 1,
        ];

        $result = Http::withHeaders($this->onlineItemsHeaders($currency))
            ->retry(3, 600, throw: false)
            ->asJson()
            ->post('https://www.bybit.com/x-api/fiat/otc/item/online', $data);

        if (! $result->successful()) {
            throw new \RuntimeException($this->describeHttpError($result->status(), $result->body()));
        }

        $payload = $result->json();
        if (! isset($payload['result']['items']) || ! is_array($payload['result']['items'])) {
            throw new \RuntimeException('ByBit API returned unexpected payload');
        }

        return $payload['result']['items'];
    }

    /**
     * @return array<string, string>
     */
    protected function onlineItemsHeaders(Currency $currency): array
    {
        return [
            'Accept' => 'application/json',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Priority' => 'u=1, i',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Content-Type' => 'application/json',
            'Lang' => 'en',
            'Platform' => 'PC',
            'Origin' => 'https://www.bybit.com',
            'Referer' => 'https://www.bybit.com/en/fiat/trade/otc/buy/USDT/'.strtoupper($currency->getCode()),
            'Sec-Ch-Ua' => '"Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
            'Sec-Ch-Ua-Mobile' => '?0',
            'Sec-Ch-Ua-Platform' => '"macOS"',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-origin',
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        ];
    }

    /**
     * Turn a raw (often HTML) provider error body into a short, human-readable message.
     */
    protected function describeHttpError(int $status, string $body): string
    {
        if ($status === 403 || stripos($body, 'Access Denied') !== false) {
            return 'ByBit временно ограничил доступ к API (Access Denied, HTTP 403). Обычно это анти-бот защита по IP — повторите попытку позже.';
        }

        return 'ByBit API error (HTTP '.$status.'): '.Str::limit(strip_tags($body), 200);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function averagePrice(array $items, ?int $minRecentOrders, int $adQuantity): float
    {
        if ($minRecentOrders !== null) {
            $items = array_values(array_filter($items, function ($item) use ($minRecentOrders) {
                return (int) ($item['recentOrderNum'] ?? 0) >= $minRecentOrders;
            }));
        }

        if (count($items) === 0) {
            return 0.0;
        }

        $prices = [];
        $take = min($adQuantity, count($items));
        for ($i = 0; $i < $take; $i++) {
            $prices[] = (float) $items[$i]['price'];
        }

        if (count($prices) === 0) {
            return 0.0;
        }

        return round(array_sum($prices) / count($prices), 6);
    }

    protected function parseBuyPrice(Currency $currency): Money
    {
        // TODO вообще должно быть наоборот
        $price = $this->parseAveragePrice($currency, true);

        return Money::fromPrecision($price, $currency);
    }

    protected function parseSellPrice(Currency $currency): Money
    {
        // TODO вообще должно быть наоборот
        $price = $this->parseAveragePrice($currency, false);

        return Money::fromPrecision($price, $currency);
    }

    public function parsePaymentMethodsList(): array
    {
        $result = Http::withHeaders([
            'accept' => 'application/json',
            'accept-language' => 'en',
            'cache-control' => 'no-cache',
            'content-type' => 'application/x-www-form-urlencoded',
            'lang' => 'en',
            'platform' => 'PC',
            'pragma' => 'no-cache',
            'priority' => 'u=1, i',
            'sec-ch-ua-mobile' => '?0',
            'User-Agent' => 'PostmanRuntime/7.42.0',
            'Accept-Encoding' => 'gzip, deflate, br',
        ])
            ->post('https://api2.bybit.com/fiat/otc/configuration/queryAllPaymentList')
            ->json();

        if ($result['ret_msg'] !== 'SUCCESS') {
            throw new \Exception('Error: '.$result['ext_info']);
        }

        if (empty($result['result'])) {
            throw new \Exception('Empty result');
        }

        return $result['result'];
    }

    protected function parseAveragePrice(Currency $currency, bool $side = true): float
    {
        $settings = services()->settings()->getMarketPriceParser($currency, MarketEnum::BYBIT);
        $sideSettings = $side ? $settings->buy : $settings->sell;

        $adQuantity = $sideSettings->ad_quantity ?: 200;
        $paymentMethods = $sideSettings->payment_methods ?: [];
        $paymentMethods = array_values(array_map(fn ($value) => strval($value), $paymentMethods));
        $minRecentOrders = $sideSettings->min_recent_orders ?: null;
        $amount = $sideSettings->amount;

        $data = [
            'userId' => '',
            'tokenId' => 'USDT',
            'currencyId' => strtoupper($currency->getCode()),
            'payment' => $paymentMethods,
            'side' => strval(intval(true)), // strval(intval($side)), //ВРЕМЕННО ПОСТВИЛ BUY, вернуть на SELL при изменении системы парсинга курсов.
            'size' => '200',
            'page' => '1',
            'amount' => $amount ? strval($amount) : '',
            'vaMaker' => true,
            'bulkMaker' => false,
            'canTrade' => true,
            'verificationFilter' => 0,
            'sortType' => 'TRADE_PRICE',
            'paymentPeriod' => [],
            'itemRegion' => 1,
        ];

        $result = Http::withHeaders($this->onlineItemsHeaders($currency))
            ->retry(3, 600, throw: false)
            ->asJson()
            ->post('https://www.bybit.com/x-api/fiat/otc/item/online', $data);

        if (! $result->successful()) {
            throw new \RuntimeException($this->describeHttpError($result->status(), $result->body()));
        }

        $payload = $result->json();
        if (! isset($payload['result']['items']) || ! is_array($payload['result']['items'])) {
            throw new \RuntimeException('ByBit API returned unexpected payload');
        }

        $items = $payload['result']['items'];

        if ($minRecentOrders !== null) {
            $items = array_values(array_filter($items, function ($item) use ($minRecentOrders) {
                return (int) ($item['recentOrderNum'] ?? 0) >= $minRecentOrders;
            }));
        }

        if (count($items) === 0) {
            return 0.0;
        }

        // Берём первые $adQuantity цен из $items, чтобы посчитать среднее арифметическое
        $prices = [];
        $take = min($adQuantity, count($items));
        for ($i = 0; $i < $take; $i++) {
            $prices[] = (float) $items[$i]['price'];
        }

        if (count($prices) === 0) {
            return 0.0;
        }

        return round(array_sum($prices) / count($prices), 6);
    }
}
// фильтры для настроек
// Зелёный «стакан»
// Bank Transfer, Cash In Person
// Объём от 200,000₽
// Только мерчанты с более чем 100 ордерами - recentOrderNum
// Среднее арифметическое первых 60 записей
