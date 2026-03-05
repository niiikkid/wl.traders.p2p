<?php

use App\Enums\MarketEnum;
use App\Models\Merchant;
use App\Services\Money\Currency;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Merchant::query()
            ->select(['id', 'settings', 'market'])
            ->chunkById(200, function ($merchants) {
                foreach ($merchants as $merchant) {
                    $settings = $merchant->settings ?? [];
                    $geoMap = $settings['geos'] ?? [];

                    if (! empty($geoMap)) {
                        continue;
                    }

                    $settings['geos'] = [
                        Currency::RUB()->getCode() => MarketEnum::RAPIRA->value,
                    ];

                    $merchant->settings = $settings;
                    $merchant->saveQuietly();
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Откат не требуется: данные можно переустановить.
    }
};
