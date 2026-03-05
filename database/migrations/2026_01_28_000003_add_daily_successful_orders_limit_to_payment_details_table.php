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
        Schema::table('payment_details', function (Blueprint $table) {
            $table->unsignedInteger('daily_successful_orders_limit')
                ->nullable()
                ->default(null)
                ->after('current_daily_limit');
            $table->unsignedInteger('current_daily_successful_orders_count')
                ->default(0)
                ->after('daily_successful_orders_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            $table->dropColumn('current_daily_successful_orders_count');
            $table->dropColumn('daily_successful_orders_limit');
        });
    }
};
