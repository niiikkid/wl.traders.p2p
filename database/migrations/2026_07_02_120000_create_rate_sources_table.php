<?php

use App\Enums\RateSourceDirection;
use App\Enums\RateSourceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_sources', function (Blueprint $table) {
            $table->id();

            $table->string('name')->nullable();
            $table->string('type')->default(RateSourceType::MANUAL->value);
            $table->string('direction')->default(RateSourceDirection::PAY_IN->value);

            $table->string('base_currency')->default('usdt');
            $table->string('quote_currency');

            // Ready rate: fiat units per 1 USDT, stored as integer-units string (see MoneyCast).
            $table->string('rate')->nullable();
            $table->string('rate_currency')->nullable();

            // Parser configuration (payment methods, ad quantity, amount, side mapping, manual rate, ...).
            $table->json('settings')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamp('last_refreshed_at')->nullable();
            $table->json('last_parse_attempt')->nullable();

            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index(['quote_currency', 'direction', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_sources');
    }
};
