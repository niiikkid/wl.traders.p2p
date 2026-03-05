<?php

declare(strict_types=1);

namespace App\Services\Market\Utils\Parser;

use App\Enums\MarketEnum;
use App\Models\ValueObjects\Settings\BinancePriceParserSideSettings;
use App\Services\Market\Value\MarketPrices;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Exception;
use Illuminate\Support\Facades\Http;

class BinanceParser extends BaseParser
{
    public function getPrices(Currency $currency): MarketPrices
    {
        if ($currency->equals(Currency::RUB())) {
            throw new Exception('Binance market supports all currencies except RUB.');
        }

        $settings = services()->settings()->getMarketPriceParser($currency, MarketEnum::BINANCE);
        //TODO вообще должно быть наоборот
        $buyPrice = $this->parseAveragePrice($currency, 'BUY', $settings->buy);
        $sellPrice = $this->parseAveragePrice($currency, 'BUY', $settings->sell); //ВРЕМЕННО ПОСТВИЛ BUY, вернуть на SELL при изменении системы парсинга курсов.

        $buyPrice = $buyPrice ?? 0.0;
        $sellPrice = $sellPrice ?? 0.0;

        return new MarketPrices(
            buyPrice: Money::fromPrecision((string) $buyPrice, $currency->getCode()),
            sellPrice: Money::fromPrecision((string) $sellPrice, $currency->getCode()),
        );
    }

    public function parseFilterConditions(Currency $currency): array
    {
        $payload = [
            'fiat' => strtoupper($currency->getCode()),
            'classifies' => ['mass', 'profession', 'fiat_trade'],
        ];

        $response = Http::withHeaders($this->defaultHeaders())
            ->post('https://p2p.binance.com/bapi/c2c/v2/public/c2c/adv/filter-conditions', $payload);

        if (! $response->successful()) {
            throw new Exception('Binance filter conditions API error: ' . $response->body());
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['data']) || ! is_array($data['data'])) {
            throw new Exception('Binance filter conditions API returned unexpected payload.');
        }

        return $this->normalizeFilterConditions($data['data']);
    }

    protected function parseAveragePrice(
        Currency $currency,
        string $tradeType,
        BinancePriceParserSideSettings $sideSettings
    ): ?float {
        $payload = [
            'rows' => 20,
            'payTypes' => $sideSettings->payment_methods ?: [],
            'countries' => $sideSettings->country ? [$sideSettings->country] : [],
            'asset' => 'USDT',
            'tradeType' => strtoupper($tradeType),
            'fiat' => strtoupper($currency->getCode()),
            'publisherType' => null,
        ];

        $items = $this->fetchBinanceAds($payload);

        $prices = [];
        $minMonthOrders = $sideSettings->min_month_orders;
        $adQuantity = $sideSettings->ad_quantity ?? 100;
        $adQuantity = max(1, min(100, $adQuantity));

        foreach ($items as $offer) {
            // Берём только "чистые" объявления без привилегий.
            if (($offer['privilegeDesc'] ?? null) !== null) {
                continue;
            }
            if (($offer['privilegeType'] ?? null) !== null) {
                continue;
            }
            if (($offer['privilegeTypeAdTotalCount'] ?? null) !== null) {
                continue;
            }

            if ($minMonthOrders !== null) {
                $monthOrders = (int) ($offer['advertiser']['monthOrderCount'] ?? 0);
                if ($monthOrders < $minMonthOrders) {
                    continue;
                }
            }

            $price = (float) ($offer['adv']['price'] ?? 0);
            if ($price <= 0) {
                continue;
            }

            $prices[] = $price;
            if (count($prices) >= $adQuantity) {
                break;
            }
        }

        if (empty($prices)) {
            return null;
        }

        return array_sum($prices) / count($prices);
    }

    protected function fetchBinanceAds(array $basePayload): array
    {
        $headers = $this->defaultHeaders();
        $rows = (int) ($basePayload['rows'] ?? 20);
        $maxPages = 5;
        $collected = [];
        $total = null;

        for ($page = 1; $page <= $maxPages; $page++) {
            $payload = array_merge($basePayload, ['page' => $page]);

            $response = Http::withHeaders($headers)
                ->post('https://p2p.binance.com/bapi/c2c/v2/friendly/c2c/adv/search', $payload);

            if (! $response->successful()) {
                throw new Exception('Binance API error: ' . $response->body());
            }

            $data = $response->json();
            $batch = $data['data'] ?? [];
            if (! is_array($batch)) {
                break;
            }

            $collected = array_merge($collected, $batch);

            if ($total === null && isset($data['total'])) {
                $total = (int) $data['total'];
            }

            if ($total !== null) {
                $pages = (int) ceil($total / max(1, $rows));
                if ($page >= min($pages, $maxPages)) {
                    break;
                }
            }

            if (count($batch) < $rows) {
                break;
            }
        }

        return $collected;
    }

    protected function normalizeFilterConditions(array $data): array
    {
        $countries = $this->normalizeCountries($data);
        $paymentMethods = $this->normalizePaymentMethods($data);

        return [
            'countries' => $countries,
            'payment_methods' => $paymentMethods,
        ];
    }

    protected function normalizeCountries(array $data): array
    {
        $list = $data['countries'] ?? [];

        if (! is_array($list)) {
            return [];
        }

        $countries = [];
        foreach ($list as $item) {
            if (is_string($item)) {
                $code = strtoupper($item);
                $countries[] = ['id' => $code, 'name' => $code];
                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $code = $this->firstNonEmpty($item, ['scode', 'country', 'countryCode', 'code', 'id']);
            $name = $this->firstNonEmpty($item, ['name', 'countryName', 'label', 'title']);

            if (! $code) {
                continue;
            }

            $code = strtoupper($code);
            $countries[] = [
                'id' => $code,
                'name' => $name ? (string) $name : $code,
            ];
        }

        return $this->uniqueById($countries);
    }

    protected function normalizePaymentMethods(array $data): array
    {
        $list = $data['tradeMethods'] ?? [];

        if (! is_array($list)) {
            return [];
        }

        $methods = [];
        foreach ($list as $item) {
            if (is_string($item)) {
                $id = trim($item);
                if ($id === '') {
                    continue;
                }
                $methods[] = ['id' => $id, 'name' => $id];
                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $id = $this->firstNonEmpty($item, ['identifier', 'payType', 'paymentType', 'id', 'code']);
            $name = $this->firstNonEmpty($item, ['tradeMethodName', 'tradeMethodShortName', 'payTypeName', 'paymentName', 'name', 'label', 'title']);

            if (! $id) {
                continue;
            }

            $methods[] = [
                'id' => (string) $id,
                'name' => $name ? (string) $name : (string) $id,
            ];
        }

        return $this->uniqueById($methods);
    }

    protected function firstNonEmpty(array $item, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $item)) {
                continue;
            }
            $value = $item[$key];
            if ($value === null || $value === '') {
                continue;
            }
            return is_scalar($value) ? (string) $value : null;
        }

        return null;
    }

    protected function uniqueById(array $items): array
    {
        $seen = [];
        $result = [];
        foreach ($items as $item) {
            $id = $item['id'] ?? null;
            if ($id === null || $id === '') {
                continue;
            }
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $result[] = $item;
        }

        return $result;
    }

    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',
        ];
    }
}
