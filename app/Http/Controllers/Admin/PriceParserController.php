<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PriceParser\UpdateRequest;
use App\Models\ValueObjects\Settings\BinancePriceParserSettings;
use App\Models\ValueObjects\Settings\CurrencyPriceParserSettings;
use App\Enums\MarketEnum;
use Illuminate\Http\Request;
use App\Models\ValueObjects\Settings\CurrencyPriceParserSideSettings;
use App\Models\ValueObjects\Settings\BinancePriceParserSideSettings;
use App\Services\Money\Currency;

class PriceParserController extends Controller
{
    public function editData(Request $request, string $currency)
    {
        $currency = new Currency($currency);
        $market = $this->resolveMarketOrFail($request->get('market'));
        $conditions = services()
            ->market()
            ->getFilterConditions($currency, $market);
        $settings = services()
            ->settings()
            ->getMarketPriceParser($currency, $market)
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'currency' => $currency->getCode(),
                'market' => $market->value,
                'filter_conditions' => $conditions,
                'settings' => $settings,
            ],
        ]);
    }

    public function update(UpdateRequest $request, string $currency)
    {
        $market = MarketEnum::from($request->validated('market'));
        $currency = new Currency($currency);

        $settings = match (true) {
            $market->equals(MarketEnum::BINANCE) => new BinancePriceParserSettings(
                buy: BinancePriceParserSideSettings::fromArray($request->validated()['buy'] ?? []),
                sell: BinancePriceParserSideSettings::fromArray($request->validated()['sell'] ?? []),
            ),
            default => new CurrencyPriceParserSettings(
                buy: CurrencyPriceParserSideSettings::fromArray($request->validated()['buy'] ?? []),
                sell: CurrencyPriceParserSideSettings::fromArray($request->validated()['sell'] ?? []),
            ),
        };

        services()->settings()->updateMarketPriceParser(
            currency: $currency,
            market: $market,
            settings: $settings
        );

        return response()->json(['success' => true]);
    }

    protected function resolveMarketOrFail(?string $market): MarketEnum
    {
        $market = $market ? strtolower($market) : null;
        $marketEnum = $market ? MarketEnum::tryFrom($market) : null;

        if (! $marketEnum) {
            abort(422, 'Не указан или некорректен параметр market.');
        }

        return $marketEnum;
    }
}
