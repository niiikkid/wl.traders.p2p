<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_rules', function (Blueprint $table) {
            $table->index(['event', 'enabled', 'currency', 'user_id'], 'nr_event_enabled_currency_user_idx');
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->index(['status', 'amount_fiat_currency', 'priority_access_until'], 'payouts_status_currency_priority_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notification_rules', function (Blueprint $table) {
            $table->dropIndex('nr_event_enabled_currency_user_idx');
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->dropIndex('payouts_status_currency_priority_idx');
        });
    }
};
