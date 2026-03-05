<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Добавление внешних ключей для таблиц orders и payment_details
     */
    public function up(): void
    {
        // Исправление данных перед добавлением внешних ключей
        // Установка NULL для user_id в payment_details, если соответствующий id не существует в таблице users
        DB::statement('UPDATE payment_details SET user_id = NULL WHERE user_id NOT IN (SELECT id FROM users)');
        
        // Проверка существования внешних ключей перед их добавлением
        $ordersForeignKeys = $this->getForeignKeys('orders');
        $paymentDetailsForeignKeys = $this->getForeignKeys('payment_details');
        
        // Добавление внешних ключей для таблицы orders
        Schema::table('orders', function (Blueprint $table) use ($ordersForeignKeys) {
            if (!in_array('fk_orders_payment_gateway_id', $ordersForeignKeys)) {
                $table->foreign('payment_gateway_id', 'fk_orders_payment_gateway_id')
                    ->references('id')
                    ->on('payment_gateways')
                    ->onDelete('set null');
            }
            
            if (!in_array('fk_orders_payment_detail_id', $ordersForeignKeys)) {
                $table->foreign('payment_detail_id', 'fk_orders_payment_detail_id')
                    ->references('id')
                    ->on('payment_details')
                    ->onDelete('set null');
            }
            
            if (!in_array('fk_orders_merchant_id', $ordersForeignKeys)) {
                $table->foreign('merchant_id', 'fk_orders_merchant_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            }
        });

        // Добавление внешних ключей для таблицы payment_details
        Schema::table('payment_details', function (Blueprint $table) use ($paymentDetailsForeignKeys) {
            if (!in_array('fk_payment_details_payment_gateway_id', $paymentDetailsForeignKeys)) {
                $table->foreign('payment_gateway_id', 'fk_payment_details_payment_gateway_id')
                    ->references('id')
                    ->on('payment_gateways')
                    ->onDelete('set null');
            }
            
            if (!in_array('fk_payment_details_user_id', $paymentDetailsForeignKeys)) {
                $table->foreign('user_id', 'fk_payment_details_user_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Откат добавления внешних ключей
     */
    public function down(): void
    {
        // Получение списка существующих внешних ключей
        $ordersForeignKeys = $this->getForeignKeys('orders');
        $paymentDetailsForeignKeys = $this->getForeignKeys('payment_details');
        
        // Удаление внешних ключей для таблицы payment_details
        Schema::table('payment_details', function (Blueprint $table) use ($paymentDetailsForeignKeys) {
            if (in_array('fk_payment_details_payment_gateway_id', $paymentDetailsForeignKeys)) {
                $table->dropForeign('fk_payment_details_payment_gateway_id');
            }
            
            if (in_array('fk_payment_details_user_id', $paymentDetailsForeignKeys)) {
                $table->dropForeign('fk_payment_details_user_id');
            }
        });

        // Удаление внешних ключей для таблицы orders
        Schema::table('orders', function (Blueprint $table) use ($ordersForeignKeys) {
            if (in_array('fk_orders_payment_gateway_id', $ordersForeignKeys)) {
                $table->dropForeign('fk_orders_payment_gateway_id');
            }
            
            if (in_array('fk_orders_payment_detail_id', $ordersForeignKeys)) {
                $table->dropForeign('fk_orders_payment_detail_id');
            }
            
            if (in_array('fk_orders_merchant_id', $ordersForeignKeys)) {
                $table->dropForeign('fk_orders_merchant_id');
            }
        });
    }

    /**
     * Получает список внешних ключей для указанной таблицы
     */
    private function getForeignKeys(string $tableName): array
    {
        $foreignKeys = [];
        $createTableSql = DB::select("SHOW CREATE TABLE {$tableName}")[0]->{'Create Table'};
        
        // Извлечение имен внешних ключей из SQL
        preg_match_all('/CONSTRAINT\s+`([^`]+)`\s+FOREIGN\s+KEY/', $createTableSql, $matches);
        
        if (isset($matches[1]) && !empty($matches[1])) {
            $foreignKeys = $matches[1];
        }
        
        return $foreignKeys;
    }
};
