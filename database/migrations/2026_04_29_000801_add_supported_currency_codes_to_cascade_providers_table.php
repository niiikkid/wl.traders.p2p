<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cascade_providers', function (Blueprint $table) {
            $table->json('supported_currency_codes')->nullable()->after('currency_code');
        });

        $allCurrencyCodes = collect(config('money.currencies', []))
            ->filter(fn (array $config): bool => ! (bool) ($config['base'] ?? false))
            ->keys()
            ->map(fn (string $currency): string => strtoupper($currency))
            ->values()
            ->all();

        DB::table('cascade_providers')
            ->orderBy('id')
            ->each(function (object $provider) use ($allCurrencyCodes): void {
                $currencyCodes = $provider->currency_code
                    ? [strtoupper((string) $provider->currency_code)]
                    : $allCurrencyCodes;

                DB::table('cascade_providers')
                    ->where('id', $provider->id)
                    ->update([
                        'supported_currency_codes' => json_encode($currencyCodes),
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cascade_providers', function (Blueprint $table) {
            $table->dropColumn('supported_currency_codes');
        });
    }
};
