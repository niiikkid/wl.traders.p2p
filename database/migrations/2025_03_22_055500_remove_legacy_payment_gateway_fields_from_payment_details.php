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
        // Получаем имена внешних ключей для sub_payment_gateway_id
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'payment_details'
            AND COLUMN_NAME = 'sub_payment_gateway_id'
            AND REFERENCED_TABLE_NAME = 'payment_gateways'
        ");

        Schema::table('payment_details', function (Blueprint $table) use ($foreignKeys) {
            // Если внешние ключи существуют, удаляем их
            if (!empty($foreignKeys)) {
                foreach ($foreignKeys as $foreignKey) {
                    $table->dropForeign($foreignKey->CONSTRAINT_NAME);
                }
            }
            
            // Удаляем устаревшие колонки
            $table->dropColumn([
                'sub_payment_gateway_id',
                'old_payment_gateway_id'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            // Восстанавливаем колонки
            $table->foreignId('sub_payment_gateway_id')->nullable();
            $table->unsignedBigInteger('old_payment_gateway_id')->nullable();
        });
    }
};
