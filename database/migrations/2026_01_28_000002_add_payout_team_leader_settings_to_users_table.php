<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('payout_referral_commission_percentage', 5, 2)
                ->default(0.00)
                ->after('team_leader_split_from_service_percent');
            $table->decimal('payout_team_leader_split_from_service_percent', 5, 2)
                ->default(0.00)
                ->after('payout_referral_commission_percentage');
        });

        DB::table('users')->update([
            'payout_referral_commission_percentage' => DB::raw('referral_commission_percentage'),
            'payout_team_leader_split_from_service_percent' => DB::raw('team_leader_split_from_service_percent'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'payout_referral_commission_percentage',
                'payout_team_leader_split_from_service_percent',
            ]);
        });
    }
};
