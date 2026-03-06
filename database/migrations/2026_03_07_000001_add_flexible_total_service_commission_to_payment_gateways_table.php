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
            if (! Schema::hasColumn('payment_gateways', 'total_service_commission_tiers_for_orders')) {
                $table->json('total_service_commission_tiers_for_orders')
                    ->nullable()
                    ->after('trader_commission_tiers_for_orders');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            if (Schema::hasColumn('payment_gateways', 'total_service_commission_tiers_for_orders')) {
                $table->dropColumn('total_service_commission_tiers_for_orders');
            }
        });
    }
};
