<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('is_vip', 'can_set_order_amount_limits');
        });

        Schema::table('payment_details', function (Blueprint $table) {
            $table->renameColumn('vip_min_order_amount_backup', 'min_order_amount_backup');
            $table->renameColumn('vip_max_order_amount_backup', 'max_order_amount_backup');
        });
    }

    public function down(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            $table->renameColumn('min_order_amount_backup', 'vip_min_order_amount_backup');
            $table->renameColumn('max_order_amount_backup', 'vip_max_order_amount_backup');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('can_set_order_amount_limits', 'is_vip');
        });
    }
};
