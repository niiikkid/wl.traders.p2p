<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MarketEnum;
use App\Http\Controllers\Controller;
use App\Services\Money\Currency;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyController extends Controller
{
    public function index(Request $request): Response
    {
        $markets = [];

        foreach (MarketEnum::cases() as $market) {
            if ($market->isDeprecated() || $market->equals(MarketEnum::MERCHANT_API)) {
                continue;
            }

            $currencies = [];

            services()->market()
                ->getSupportedCurrencies($market)
                ->each(function (Currency $currency) use (&$currencies, $market) {
                    $buyPrice = services()->market()->getBuyPrice($currency, $market, false);
                    $sellPrice = services()->market()->getSellPrice($currency, $market, false);

                    $currencies[] = [
                        'code' => $currency->getCode(),
                        'symbol' => $currency->getSymbol(),
                        'name' => $currency->getName(),
                        'buy_price' => $buyPrice->toBeauty(),
                        'sell_price' => $sellPrice->toBeauty(),
                    ];
                });

            $markets[$market->value] = $currencies;
        }

        $marketKeys = array_keys($markets);
        $market = $request->query('market');

        if (! is_string($market) || ! in_array($market, $marketKeys, true)) {
            $market = $marketKeys[0] ?? null;
        }

        return Inertia::render('Currency/Index', compact('markets', 'market'));
    }
}
