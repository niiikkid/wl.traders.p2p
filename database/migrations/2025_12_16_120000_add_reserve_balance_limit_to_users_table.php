<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('reserve_balance_limit')->nullable()->after('stop_traffic');
        });

        // Для всех существующих пользователей: как было "захардкожено" ранее
        DB::table('users')->update([
            'reserve_balance_limit' => 1000,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('reserve_balance_limit');
        });
    }
};


