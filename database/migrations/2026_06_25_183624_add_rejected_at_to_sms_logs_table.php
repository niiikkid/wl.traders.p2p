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
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('order_id');
            $table->index('rejected_at', 'idx_sms_logs_rejected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropIndex('idx_sms_logs_rejected_at');
            $table->dropColumn('rejected_at');
        });
    }
};
