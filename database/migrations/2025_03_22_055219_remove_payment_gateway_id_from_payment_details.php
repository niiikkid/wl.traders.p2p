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
        // Получаем имя внешнего ключа
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'payment_details'
            AND COLUMN_NAME = 'payment_gateway_id'
            AND REFERENCED_TABLE_NAME = 'payment_gateways'
        ");

        Schema::table('payment_details', function (Blueprint $table) use ($foreignKeys) {
            // Если внешний ключ существует, удаляем его
            if (!empty($foreignKeys)) {
                foreach ($foreignKeys as $foreignKey) {
                    $table->dropForeign($foreignKey->CONSTRAINT_NAME);
                }
            }
            
            // Удаляем колонку
            $table->dropColumn('payment_gateway_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_details', function (Blueprint $table) {
            // Восстанавливаем колонку
            $table->foreignId('payment_gateway_id')->nullable();
        });
    }
};
