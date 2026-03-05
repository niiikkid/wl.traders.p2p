<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MarketEnum;
use App\Http\Controllers\Controller;
use App\Services\Money\Currency;
use Inertia\Inertia;

class CurrencyController extends Controller
{
    public function index()
    {
        $markets = [];

        foreach (MarketEnum::cases() as $market) {
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

        return Inertia::render('Currency/Index', compact('markets'));
    }
}
