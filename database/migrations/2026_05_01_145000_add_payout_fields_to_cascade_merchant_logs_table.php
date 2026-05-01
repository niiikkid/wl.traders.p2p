<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cascade_merchant_logs', function (Blueprint $table) {
            $table->foreignId('payout_id')
                ->nullable()
                ->after('cascade_deal_id')
                ->constrained('payouts')
                ->cascadeOnDelete();
            $table->string('payment_type')
                ->default('payin')
                ->after('merchant_id');

            $table->index('payout_id');
            $table->index('payment_type');
        });
    }

    public function down(): void
    {
        Schema::table('cascade_merchant_logs', function (Blueprint $table) {
            $table->dropForeign(['payout_id']);
            $table->dropIndex(['payout_id']);
            $table->dropIndex(['payment_type']);
            $table->dropColumn(['payout_id', 'payment_type']);
        });
    }
};
