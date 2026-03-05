<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Добавление индексов для таблицы wallets
     */
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->index('merchant_balance', 'idx_wallets_merchant_balance');
            $table->index('trust_balance', 'idx_wallets_trust_balance');
            $table->index('reserve_balance', 'idx_wallets_reserve_balance');
            $table->index('commission_balance', 'idx_wallets_commission_balance');
            $table->index('user_id', 'idx_wallets_user_id');
        });
    }

    /**
     * Откат добавления индексов
     */
    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropIndex('idx_wallets_merchant_balance');
            $table->dropIndex('idx_wallets_trust_balance');
            $table->dropIndex('idx_wallets_reserve_balance');
            $table->dropIndex('idx_wallets_commission_balance');
            $table->dropIndex('idx_wallets_user_id');
        });
    }
};
