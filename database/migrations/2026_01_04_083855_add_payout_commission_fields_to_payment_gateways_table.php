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
            if (! Schema::hasColumn('payment_gateways', 'trader_commission_rate_for_payouts')) {
                $table->float('trader_commission_rate_for_payouts', 8, 2)->default(0)->after('trader_commission_rate_for_orders');
            }
            
            if (! Schema::hasColumn('payment_gateways', 'total_service_commission_rate_for_payouts')) {
                $table->float('total_service_commission_rate_for_payouts', 8, 2)->default(0)->after('total_service_commission_rate_for_orders');
            }
            
            if (! Schema::hasColumn('payment_gateways', 'reservation_time_for_payouts')) {
                $table->unsignedInteger('reservation_time_for_payouts')->default(10)->after('reservation_time_for_orders');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            if (Schema::hasColumn('payment_gateways', 'trader_commission_rate_for_payouts')) {
                $table->dropColumn('trader_commission_rate_for_payouts');
            }
            
            if (Schema::hasColumn('payment_gateways', 'total_service_commission_rate_for_payouts')) {
                $table->dropColumn('total_service_commission_rate_for_payouts');
            }
            
            if (Schema::hasColumn('payment_gateways', 'reservation_time_for_payouts')) {
                $table->dropColumn('reservation_time_for_payouts');
            }
        });
    }
};
