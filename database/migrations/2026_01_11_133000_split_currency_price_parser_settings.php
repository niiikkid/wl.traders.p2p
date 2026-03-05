<?php

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
            if (isset($config['buy']) || isset($config['sell'])) {
                $updated[$code] = $config;
                continue;
            }

            $updated[$code] = CurrencyPriceParserSettings::fromArray($config)->toArray();
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
            if (! isset($config['buy']) && ! isset($config['sell'])) {
                $rolledBack[$code] = $config;
                continue;
            }

            $side = $config['buy'] ?? $config['sell'] ?? [];
            $rolledBack[$code] = [
                'amount' => $side['amount'] ?? null,
                'payment_methods' => $side['payment_methods'] ?? [],
                'ad_quantity' => $side['ad_quantity'] ?? null,
                'min_recent_orders' => $side['min_recent_orders'] ?? null,
            ];
        }

        DB::table('settings')
            ->where('key', SettingsService::CURRENCY_PRICE_PARSER_SETTINGS)
            ->update(['value' => json_encode($rolledBack)]);
    }
};
