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
        Schema::table('user_metas', function (Blueprint $table) {
            $table->float('order_service_commission_rate', 2)->nullable()->after('id');
            $table->float('payout_service_commission_rate', 2)->nullable()->after('order_service_commission_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_metas', function (Blueprint $table) {
            $table->dropColumn('order_service_commission_rate');
            $table->dropColumn('payout_service_commission_rate');
        });
    }
};
