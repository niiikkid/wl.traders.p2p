<?php

use App\Enums\MarketEnum;
use App\Models\Merchant;
use App\Models\UserMeta;
use App\Services\Money\Currency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('merchants')
            ->where('market', MarketEnum::RAPIRA->value)
            ->update(['market' => MarketEnum::BYBIT->value]);

        Merchant::query()
            ->select(['id', 'settings'])
            ->chunkById(200, function ($merchants): void {
                foreach ($merchants as $merchant) {
                    $settings = $merchant->settings ?? [];
                    $geoMap = $settings['geos'] ?? [];

                    if ($geoMap === []) {
                        continue;
                    }

                    $updated = false;

                    foreach ($geoMap as $currencyCode => $marketValue) {
                        if ($marketValue === MarketEnum::RAPIRA->value) {
                            $geoMap[$currencyCode] = MarketEnum::BYBIT->value;
                            $updated = true;
                        }
                    }

                    if (! $updated) {
                        continue;
                    }

                    $settings['geos'] = $geoMap;
                    $merchant->settings = $settings;
                    $merchant->saveQuietly();
                }
            });

        UserMeta::query()
            ->select(['id', 'allowed_markets'])
            ->whereNotNull('allowed_markets')
            ->chunkById(200, function ($userMetas): void {
                foreach ($userMetas as $userMeta) {
                    $allowedMarkets = $userMeta->allowed_markets;

                    if (! is_array($allowedMarkets) || ! in_array(MarketEnum::RAPIRA->value, $allowedMarkets, true)) {
                        continue;
                    }

                    $userMeta->allowed_markets = array_values(array_unique(array_map(
                        fn (string $market) => $market === MarketEnum::RAPIRA->value
                            ? MarketEnum::BYBIT->value
                            : $market,
                        $allowedMarkets
                    )));

                    $userMeta->saveQuietly();
                }
            });

        Cache::forget('conversion-price-for-'.Currency::RUB()->getCode().'-'.MarketEnum::RAPIRA->value);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
