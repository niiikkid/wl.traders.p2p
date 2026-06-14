<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payouts')
            ->where('status', 'open')
            ->whereNull('trader_id')
            ->whereNotNull('priority_access_until')
            ->where('priority_access_until', '>', now())
            ->update(['priority_access_until' => null]);

        Schema::table('payouts', function (Blueprint $table) {
            $table->dropIndex('payouts_status_currency_priority_idx');
            $table->index(['status', 'amount_fiat_currency'], 'payouts_status_currency_idx');
            $table->dropIndex(['priority_access_until']);
            $table->dropColumn('priority_access_until');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('priority_payout_access_enabled');
        });

        DB::table('settings')
            ->whereIn('key', [
                'payout_priority_access_enabled',
                'payout_priority_access_delay_minutes',
                'payout_priority_access_release_without_online_traders',
            ])
            ->delete();

        $payoutCurrencySettings = DB::table('settings')
            ->where('key', 'payout_currency_settings')
            ->value('value');

        if (is_string($payoutCurrencySettings) && $payoutCurrencySettings !== '') {
            $settings = json_decode($payoutCurrencySettings, true);

            if (is_array($settings)) {
                foreach ($settings as $currency => $currencySettings) {
                    if (! is_array($currencySettings)) {
                        continue;
                    }

                    unset(
                        $settings[$currency]['priority_access_min_amount'],
                        $settings[$currency]['priority_access_max_amount'],
                    );
                }

                DB::table('settings')
                    ->where('key', 'payout_currency_settings')
                    ->update(['value' => json_encode($settings)]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('priority_payout_access_enabled')
                ->default(false)
                ->after('payouts_enabled');
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->dropIndex('payouts_status_currency_idx');
            $table->timestamp('priority_access_until')
                ->nullable()
                ->after('expires_at')
                ->index();
            $table->index(
                ['status', 'amount_fiat_currency', 'priority_access_until'],
                'payouts_status_currency_priority_idx'
            );
        });

        DB::table('settings')->insertOrIgnore([
            [
                'key' => 'payout_priority_access_enabled',
                'value' => 0,
            ],
            [
                'key' => 'payout_priority_access_delay_minutes',
                'value' => 10,
            ],
            [
                'key' => 'payout_priority_access_release_without_online_traders',
                'value' => 1,
            ],
        ]);
    }
};
