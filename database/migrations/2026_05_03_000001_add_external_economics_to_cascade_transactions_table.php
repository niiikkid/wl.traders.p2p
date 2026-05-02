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
        Schema::table('cascade_transactions', function (Blueprint $table) {
            $table->string('usdt_amount')->nullable()->after('provider_deal_id');
            $table->string('fee')->nullable()->after('usdt_amount');
            $table->float('fee_rate', 8, 2)->nullable()->after('fee');
            $table->string('credit')->nullable()->after('fee_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cascade_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'usdt_amount',
                'fee',
                'fee_rate',
                'credit',
            ]);
        });
    }
};
