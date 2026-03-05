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
            $table->renameColumn('schema', 'nspk_schema');
            $table->renameColumn('buy_price_markup_rate', 'trader_commission_rate_for_orders');
            $table->renameColumn('sell_price_markup_rate', 'trader_commission_rate_for_payouts');
            $table->renameColumn('order_service_commission_rate', 'total_service_commission_rate_for_orders');
            $table->renameColumn('payout_service_commission_rate', 'total_service_commission_rate_for_payouts');
            $table->renameColumn('reservation_time', 'reservation_time_for_orders');
            $table->renameColumn('payout_reservation_time', 'reservation_time_for_payouts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->renameColumn('nspk_schema', 'schema');
            $table->renameColumn('trader_commission_rate_for_orders', 'buy_price_markup_rate');
            $table->renameColumn('trader_commission_rate_for_payouts', 'sell_price_markup_rate');
            $table->renameColumn('total_service_commission_rate_for_orders', 'order_service_commission_rate');
            $table->renameColumn('total_service_commission_rate_for_payouts', 'payout_service_commission_rate');
            $table->renameColumn('reservation_time_for_orders', 'reservation_time');
            $table->renameColumn('reservation_time_for_payouts', 'payout_reservation_time');
        });
    }
};
