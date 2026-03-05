<?php

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

        $updated = [];

        Currency::getAll()->each(function (Currency $currency) use (&$updated) {
            $code = $currency->getCode();
            $updated[$code] = [
                'amount' => null,
                'payment_methods' => [],
                'ad_quantity' => 50,
                'min_recent_orders' => 100,
            ];
        });

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

        $rolledBack = [];

        Currency::getAll()->each(function (Currency $currency) use (&$rolledBack) {
            $code = $currency->getCode();
            $rolledBack[$code] = [
                'amount' => null,
                'payment_method' => null,
                'ad_quantity' => 3,
            ];
        });

        DB::table('settings')
            ->where('key', SettingsService::CURRENCY_PRICE_PARSER_SETTINGS)
            ->update(['value' => json_encode($rolledBack)]);
    }
};
