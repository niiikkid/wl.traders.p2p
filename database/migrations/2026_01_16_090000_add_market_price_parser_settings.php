<?php

use App\Models\ValueObjects\Settings\BinancePriceParserSettings;
use App\Models\ValueObjects\Settings\CurrencyPriceParserSettings;
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

        $stored = json_decode($setting->value, true) ?? [];
        $updated = [];

        foreach ($stored as $code => $config) {
            if (isset($config['bybit']) || isset($config['binance'])) {
                $updated[$code] = [
                    'bybit' => CurrencyPriceParserSettings::fromArray($config['bybit'] ?? [])->toArray(),
                    'binance' => BinancePriceParserSettings::fromArray($config['binance'] ?? [])->toArray(),
                ];
                continue;
            }

            $updated[$code] = [
                'bybit' => CurrencyPriceParserSettings::fromArray($config)->toArray(),
                'binance' => BinancePriceParserSettings::defaults()->toArray(),
            ];
        }

        DB::table('settings')
            ->where('key', SettingsService::CURRENCY_PRICE_PARSER_SETTINGS)
            ->update(['value' => json_encode($updated)]);
    }

    public function down(): void
    {
        $setting = DB::table('settings')
            ->where('key', SettingsService::CURRENCY_PRICE_PARSER_SETTINGS)
            ->first();

        if (! $setting) {
            return;
        }

        $stored = json_decode($setting->value, true) ?? [];
        $rolledBack = [];

        foreach ($stored as $code => $config) {
            if (isset($config['bybit'])) {
                $rolledBack[$code] = $config['bybit'];
            } else {
                $rolledBack[$code] = $config;
            }
        }

        DB::table('settings')
            ->where('key', SettingsService::CURRENCY_PRICE_PARSER_SETTINGS)
            ->update(['value' => json_encode($rolledBack)]);
    }
};
