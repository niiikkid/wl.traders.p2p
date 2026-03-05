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
        Schema::table('payouts', function (Blueprint $table) {
            $table->string('teamlead_split_from_service')->nullable()->after('teamlead_fee_currency');
            $table->string('teamlead_split_from_service_currency')->default('usdt')->after('teamlead_split_from_service');
            $table->string('teamlead_split_from_trader')->nullable()->after('teamlead_split_from_service_currency');
            $table->string('teamlead_split_from_trader_currency')->default('usdt')->after('teamlead_split_from_trader');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn('teamlead_split_from_service');
            $table->dropColumn('teamlead_split_from_service_currency');
            $table->dropColumn('teamlead_split_from_trader');
            $table->dropColumn('teamlead_split_from_trader_currency');
        });
    }
};
