<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_gateways', 'use_flexible_trader_commission_for_orders')) {
                $table->boolean('use_flexible_trader_commission_for_orders')
                    ->default(false)
                    ->after('trader_commission_rate_for_orders');
            }

            if (! Schema::hasColumn('payment_gateways', 'trader_commission_tiers_for_orders')) {
                $table->json('trader_commission_tiers_for_orders')
                    ->nullable()
                    ->after('use_flexible_trader_commission_for_orders');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            if (Schema::hasColumn('payment_gateways', 'trader_commission_tiers_for_orders')) {
                $table->dropColumn('trader_commission_tiers_for_orders');
            }

            if (Schema::hasColumn('payment_gateways', 'use_flexible_trader_commission_for_orders')) {
                $table->dropColumn('use_flexible_trader_commission_for_orders');
            }
        });
    }
};
