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
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('rate_fixed_at')->nullable()->after('conversion_price');
            $table->string('team_leader_split_from_service')->nullable()->after('team_leader_commission_rate');
            $table->string('team_leader_split_from_trader')->nullable()->after('team_leader_split_from_service');
            $table->json('calc_meta')->nullable()->after('amount_updates_history');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('rate_fixed_at');
            $table->dropColumn('team_leader_split_from_service');
            $table->dropColumn('team_leader_split_from_trader');
            $table->dropColumn('calc_meta');
        });
    }
};
