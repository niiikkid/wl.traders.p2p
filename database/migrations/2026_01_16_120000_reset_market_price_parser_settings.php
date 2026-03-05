<?php

use App\Models\ValueObjects\Settings\BinancePriceParserSettings;
use App\Models\ValueObjects\Settings\CurrencyPriceParserSettings;
use App\Services\Money\Currency;
use App\Services\Settings\SettingsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $setting = DB::table('settings')
            ->where('key', SettingsService::CURRENCY_PRICE_PARSER_SETTINGS)
            ->first();

        if (! $setting) {
            return;
        }

        $defaults = [];
        Currency::getAll()->each(function (Currency $currency) use (&$defaults) {
            $code = $currency->getCode();
            $defaults[$code] = [
                'bybit' => CurrencyPriceParserSettings::defaults()->toArray(),
                'binance' => BinancePriceParserSettings::defaults()->toArray(),
            ];
        });

        DB::table('settings')
            ->where('key', SettingsService::CURRENCY_PRICE_PARSER_SETTINGS)
            ->update(['value' => json_encode($defaults)]);
    }

    public function down(): void
    {
        // intentionally left blank
    }
};
