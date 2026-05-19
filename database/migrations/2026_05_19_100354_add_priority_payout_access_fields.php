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
            $table->boolean('priority_payout_access_enabled')
                ->default(false)
                ->after('payouts_enabled');
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->timestamp('priority_access_until')
                ->nullable()
                ->after('expires_at')
                ->index();
        });

        DB::table('settings')->insertOrIgnore([
            [
                'key' => 'payout_priority_access_enabled',
                'value' => 0,
            ],
            [
                'key' => 'payout_priority_access_delay_minutes',
                'value' => 10,
            ],
            [
                'key' => 'payout_priority_access_release_without_online_traders',
                'value' => 1,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropIndex(['priority_access_until']);
            $table->dropColumn('priority_access_until');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('priority_payout_access_enabled');
        });

        DB::table('settings')
            ->whereIn('key', [
                'payout_priority_access_enabled',
                'payout_priority_access_delay_minutes',
                'payout_priority_access_release_without_online_traders',
            ])
            ->delete();
    }
};
