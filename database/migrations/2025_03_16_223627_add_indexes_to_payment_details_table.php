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
        Schema::table('payment_details', function (Blueprint $table) {
            $table->index('detail_type', 'idx_payment_details_detail_type');
            $table->index('daily_limit', 'idx_payment_details_daily_limit');
            $table->index('current_daily_limit', 'idx_payment_details_current_daily_limit');
            $table->index('max_pending_orders_quantity', 'idx_payment_details_max_pending_orders_quantity');
            $table->index('user_device_id', 'idx_payment_details_user_device_id');
            $table->index('last_used_at', 'idx_payment_details_last_used_at');
            $table->index('archived_at', 'idx_payment_details_archived_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            $table->dropIndex('idx_payment_details_detail_type');
            $table->dropIndex('idx_payment_details_daily_limit');
            $table->dropIndex('idx_payment_details_current_daily_limit');
            $table->dropIndex('idx_payment_details_max_pending_orders_quantity');
            $table->dropIndex('idx_payment_details_user_device_id');
            $table->dropIndex('idx_payment_details_last_used_at');
            $table->dropIndex('idx_payment_details_archived_at');
        });
    }
};
