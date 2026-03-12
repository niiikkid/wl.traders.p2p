<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'team_leader_flexible_trader_commission_enabled')) {
                $table->boolean('team_leader_flexible_trader_commission_enabled')
                    ->default(false)
                    ->after('team_leader_extended_access_enabled');
            }

            if (! Schema::hasColumn('users', 'team_leader_flexible_trader_commission_min')) {
                $table->decimal('team_leader_flexible_trader_commission_min', 5, 2)
                    ->nullable()
                    ->after('team_leader_flexible_trader_commission_enabled');
            }

            if (! Schema::hasColumn('users', 'team_leader_flexible_trader_commission_max')) {
                $table->decimal('team_leader_flexible_trader_commission_max', 5, 2)
                    ->nullable()
                    ->after('team_leader_flexible_trader_commission_min');
            }

            if (! Schema::hasColumn('users', 'team_leader_individual_commission_percentage')) {
                $table->decimal('team_leader_individual_commission_percentage', 5, 2)
                    ->nullable()
                    ->after('referral_commission_percentage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'team_leader_individual_commission_percentage')) {
                $table->dropColumn('team_leader_individual_commission_percentage');
            }

            if (Schema::hasColumn('users', 'team_leader_flexible_trader_commission_max')) {
                $table->dropColumn('team_leader_flexible_trader_commission_max');
            }

            if (Schema::hasColumn('users', 'team_leader_flexible_trader_commission_min')) {
                $table->dropColumn('team_leader_flexible_trader_commission_min');
            }

            if (Schema::hasColumn('users', 'team_leader_flexible_trader_commission_enabled')) {
                $table->dropColumn('team_leader_flexible_trader_commission_enabled');
            }
        });
    }
};
