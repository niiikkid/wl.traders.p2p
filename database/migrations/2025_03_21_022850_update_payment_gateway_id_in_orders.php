<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Создаем временную колонку для хранения старых значений
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('old_payment_gateway_id')->nullable()->after('payment_gateway_id');
        });

        // Сохраняем старые значения и обновляем payment_gateway_id
        DB::table('orders')
            ->join('payment_details', 'orders.payment_detail_id', '=', 'payment_details.id')
            ->where('orders.payment_gateway_id', 4)
            ->update([
                'orders.old_payment_gateway_id' => DB::raw('orders.payment_gateway_id'),
                'orders.payment_gateway_id' => DB::raw('payment_details.payment_gateway_id')
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Восстанавливаем старые значения
        DB::table('orders')
            ->whereNotNull('old_payment_gateway_id')
            ->update([
                'payment_gateway_id' => DB::raw('old_payment_gateway_id')
            ]);

        // Удаляем временную колонку
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('old_payment_gateway_id');
        });
    }
};
