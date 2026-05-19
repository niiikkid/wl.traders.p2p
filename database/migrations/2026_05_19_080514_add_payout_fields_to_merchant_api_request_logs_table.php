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
        Schema::table('merchant_api_request_logs', function (Blueprint $table) {
            $table->string('request_type', 20)->default('order')->after('id')->index();
            $table->foreignId('payout_id')->nullable()->after('order_id')->constrained('payouts')->nullOnDelete();
        });

        // Все существующие записи — логи сделок; явно проставляем order (на случай NULL после ADD).
        DB::table('merchant_api_request_logs')
            ->whereNull('request_type')
            ->update(['request_type' => 'order']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_api_request_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_id');
            $table->dropColumn('request_type');
        });
    }
};
