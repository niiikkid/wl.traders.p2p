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
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('has_pending_dispute')
                ->default(false)
                ->after('status');
            $table->index(['has_pending_dispute', 'id'], 'idx_orders_pending_dispute_id');
        });

        DB::statement('
            UPDATE orders
            SET has_pending_dispute = 1
            WHERE EXISTS (
                SELECT 1
                FROM disputes
                WHERE disputes.order_id = orders.id
                  AND disputes.status = "pending"
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_pending_dispute_id');
            $table->dropColumn('has_pending_dispute');
        });
    }
};
