<?php

use App\Models\PaymentGateway;
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
            $table->float('buy_price_markup_rate', 2)->default(2.5)->after('service_commission_rate');
            $table->float('sell_price_markup_rate', 2)->default(2.5)->after('buy_price_markup_rate');

            $table->float('order_service_commission_rate', 2)->default(9)->after('sell_price_markup_rate');
            $table->float('payout_service_commission_rate', 2)->default(9)->after('order_service_commission_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->dropColumn('buy_price_markup_rate');
            $table->dropColumn('sell_price_markup_rate');
            $table->dropColumn('order_service_commission_rate');
            $table->dropColumn('payout_service_commission_rate');
        });
    }
};
