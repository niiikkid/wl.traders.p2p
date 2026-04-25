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
        Schema::table('cascade_deals', function (Blueprint $table) {
            $table->foreign('selected_transaction_id', 'fk_cascade_deals_selected_transaction_id')
                ->references('id')
                ->on('cascade_transactions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cascade_deals', function (Blueprint $table) {
            $table->dropForeign('fk_cascade_deals_selected_transaction_id');
        });
    }
};
