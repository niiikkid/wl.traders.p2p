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
        Schema::table('disputes', function (Blueprint $table) {
            $table->index('status', 'idx_disputes_status');
            $table->index('trader_id', 'idx_disputes_trader_id');
            $table->index('order_id', 'idx_disputes_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropIndex('idx_disputes_status');
            $table->dropIndex('idx_disputes_trader_id');
            $table->dropIndex('idx_disputes_order_id');
        });
    }
};
