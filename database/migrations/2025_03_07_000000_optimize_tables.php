<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Оптимизация таблиц orders, payment_details, payment_gateways и users
     * без изменения структуры данных
     */
    public function up(): void
    {
        // Добавление индексов для таблицы orders
        Schema::table('orders', function (Blueprint $table) {
            $table->index('uuid', 'idx_orders_uuid');
            $table->index('external_id', 'idx_orders_external_id');
            $table->index('status', 'idx_orders_status');
            $table->index(['merchant_id', 'status'], 'idx_orders_merchant_status');
            $table->index('created_at', 'idx_orders_created_at');
            $table->index('expires_at', 'idx_orders_expires_at');
            $table->index('payment_gateway_id', 'idx_orders_payment_gateway_id');
            $table->index('payment_detail_id', 'idx_orders_payment_detail_id');
        });

        // Добавление индексов для таблицы payment_details
        Schema::table('payment_details', function (Blueprint $table) {
            $table->index('user_id', 'idx_payment_details_user_id');
            $table->index('payment_gateway_id', 'idx_payment_details_payment_gateway_id');
            $table->index('is_active', 'idx_payment_details_is_active');
            $table->index('currency', 'idx_payment_details_currency');
            $table->index(['user_id', 'is_active'], 'idx_payment_details_user_active');
        });

        // Добавление индексов для таблицы payment_gateways
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->index('code', 'idx_payment_gateways_code');
            $table->index('currency', 'idx_payment_gateways_currency');
            $table->index('is_active', 'idx_payment_gateways_is_active');
            $table->index(['currency', 'is_active'], 'idx_payment_gateways_currency_active');
        });

        // Добавление индексов для таблицы users
        Schema::table('users', function (Blueprint $table) {
            $table->index('banned_at', 'idx_users_banned_at');
            $table->index('created_at', 'idx_users_created_at');
        });
    }

    /**
     * Откат оптимизаций
     */
    public function down(): void
    {
        // Удаление индексов для таблицы users
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_banned_at');
            $table->dropIndex('idx_users_created_at');
        });

        // Удаление индексов для таблицы payment_gateways
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->dropIndex('idx_payment_gateways_code');
            $table->dropIndex('idx_payment_gateways_currency');
            $table->dropIndex('idx_payment_gateways_is_active');
            $table->dropIndex('idx_payment_gateways_currency_active');
        });

        // Удаление индексов для таблицы payment_details
        Schema::table('payment_details', function (Blueprint $table) {
            $table->dropIndex('idx_payment_details_user_id');
            $table->dropIndex('idx_payment_details_payment_gateway_id');
            $table->dropIndex('idx_payment_details_is_active');
            $table->dropIndex('idx_payment_details_currency');
            $table->dropIndex('idx_payment_details_user_active');
        });

        // Удаление индексов для таблицы orders
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_uuid');
            $table->dropIndex('idx_orders_external_id');
            $table->dropIndex('idx_orders_status');
            $table->dropIndex('idx_orders_merchant_status');
            $table->dropIndex('idx_orders_created_at');
            $table->dropIndex('idx_orders_expires_at');
            $table->dropIndex('idx_orders_payment_gateway_id');
            $table->dropIndex('idx_orders_payment_detail_id');
        });
    }
}; 