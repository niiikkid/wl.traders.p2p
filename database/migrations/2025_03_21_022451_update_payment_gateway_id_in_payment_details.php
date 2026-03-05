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
        Schema::table('payment_details', function (Blueprint $table) {
            $table->unsignedBigInteger('old_payment_gateway_id')->nullable()->after('payment_gateway_id');
        });

        // Сохраняем старые значения и обновляем payment_gateway_id
        DB::table('payment_details')
            ->where('payment_gateway_id', 4)
            ->where('sub_payment_gateway_id', '!=', null)
            ->update([
                'old_payment_gateway_id' => DB::raw('payment_gateway_id'),
                'payment_gateway_id' => DB::raw('sub_payment_gateway_id')
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Восстанавливаем старые значения
        DB::table('payment_details')
            ->whereNotNull('old_payment_gateway_id')
            ->update([
                'payment_gateway_id' => DB::raw('old_payment_gateway_id')
            ]);

        // Удаляем временную колонку
        Schema::table('payment_details', function (Blueprint $table) {
            $table->dropColumn('old_payment_gateway_id');
        });
    }
};
