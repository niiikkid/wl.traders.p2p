<?php

namespace App\Services\Market\Utils\Parser;

use App\Services\Market\Value\MarketPrices;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Exception;
use Illuminate\Support\Facades\Http;

class RapiraParser extends BaseParser
{
    public function getPrices(Currency $currency): MarketPrices
    {
        if (! $currency->equals(Currency::RUB())) {
            throw new Exception('Rapira market supports only RUB.');
        }

        [$buyPrice, $sellPrice] = $this->getRapiraPrices();

        return new MarketPrices(
            buyPrice: Money::fromPrecision($buyPrice, $currency),
            sellPrice: Money::fromPrecision($sellPrice, $currency),
        );
    }

    public function getRapiraPrices(): array
    {
        $url = "https://api.rapira.net/market/exchange-plate-mini?symbol=USDT/RUB";
        $response = Http::get($url);

        if ($response->failed()) {
            throw new Exception("Не удалось получить данные от Rapira API.");
        }

        $data = $response->json();

        if (empty($data['ask']['items']) || empty($data['bid']['items'])) {
            throw new Exception("Нет данных о стакане заявок.");
        }

        $askItems = $data['ask']['items'];
        $bidItems = $data['bid']['items'];

        $bestBid = $bidItems[0]['price']; // верхняя запись зелёного стакана
        $bestAsk = $askItems[0]['price']; // нижняя запись красного стакана

        return [$bestBid, $bestAsk];
    }
}
