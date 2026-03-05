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
        if (! Schema::hasTable('users')) {
            return;
        }

        // 1) Гарантируем наличие колонки payouts_enabled
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'payouts_enabled')) {
                $table->boolean('payouts_enabled')
                    ->default(true)
                    ->after('banned_at');
            }
        });

        // 2) Для всех существующих пользователей выставляем payouts_enabled = true,
        // если поле NULL или false
        DB::table('users')
            ->where(function ($query) {
                $query->whereNull('payouts_enabled')
                    ->orWhere('payouts_enabled', false);
            })
            ->update([
                'payouts_enabled' => true,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Ничего не откатываем, так как это безопасное массовое обновление флага
    }
};


