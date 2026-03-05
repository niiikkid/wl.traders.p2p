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
            $table->string('vip_min_order_amount_backup')->nullable()->after('max_order_amount');
            $table->string('vip_max_order_amount_backup')->nullable()->after('vip_min_order_amount_backup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            $table->dropColumn([
                'vip_min_order_amount_backup',
                'vip_max_order_amount_backup',
            ]);
        });
    }
};

