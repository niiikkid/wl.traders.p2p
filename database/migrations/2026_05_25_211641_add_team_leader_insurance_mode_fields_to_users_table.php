<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'team_leader_insurance_mode')) {
                $table->string('team_leader_insurance_mode', 32)
                    ->default('trader_reserve')
                    ->after('team_leader_flexible_trader_commission_max');
            }

            if (! Schema::hasColumn('users', 'team_leader_trader_limit')) {
                $table->unsignedInteger('team_leader_trader_limit')
                    ->nullable()
                    ->after('team_leader_insurance_mode');
            }

            if (! Schema::hasColumn('users', 'team_leader_reserve_balance_limit')) {
                $table->unsignedInteger('team_leader_reserve_balance_limit')
                    ->nullable()
                    ->after('team_leader_trader_limit');
            }

            if (! Schema::hasColumn('users', 'team_leader_reserve_stop_threshold')) {
                $table->unsignedInteger('team_leader_reserve_stop_threshold')
                    ->nullable()
                    ->after('team_leader_reserve_balance_limit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'team_leader_reserve_stop_threshold')) {
                $table->dropColumn('team_leader_reserve_stop_threshold');
            }

            if (Schema::hasColumn('users', 'team_leader_reserve_balance_limit')) {
                $table->dropColumn('team_leader_reserve_balance_limit');
            }

            if (Schema::hasColumn('users', 'team_leader_trader_limit')) {
                $table->dropColumn('team_leader_trader_limit');
            }

            if (Schema::hasColumn('users', 'team_leader_insurance_mode')) {
                $table->dropColumn('team_leader_insurance_mode');
            }
        });
    }
};
