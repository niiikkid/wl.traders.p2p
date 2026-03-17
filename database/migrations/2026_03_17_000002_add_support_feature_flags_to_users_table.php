<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('support_can_view_deposits')
                ->default(false)
                ->after('team_leader_flexible_trader_commission_max');
            $table->boolean('support_can_edit_order_amount')
                ->default(false)
                ->after('support_can_view_deposits');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'support_can_view_deposits',
                'support_can_edit_order_amount',
            ]);
        });
    }
};
