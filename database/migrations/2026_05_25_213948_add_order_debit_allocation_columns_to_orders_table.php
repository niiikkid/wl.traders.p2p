<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('trader_trust_paid_for_order')->nullable()->after('trader_paid_for_order');
            $table->string('team_leader_reserve_paid_for_order')->nullable()->after('trader_trust_paid_for_order');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'trader_trust_paid_for_order',
                'team_leader_reserve_paid_for_order',
            ]);
        });
    }
};
