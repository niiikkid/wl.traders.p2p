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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('payout_hold_enabled')->default(true)->after('banned_at');
            $table->unsignedInteger('payout_hold_minutes')->default(60)->after('payout_hold_enabled');
            $table->unsignedInteger('payout_active_payouts_limit')->default(1)->after('payout_hold_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'payout_hold_enabled',
                'payout_hold_minutes',
                'payout_active_payouts_limit',
            ]);
        });
    }
};


