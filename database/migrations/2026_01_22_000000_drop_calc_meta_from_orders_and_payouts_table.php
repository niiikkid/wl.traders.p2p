<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'calc_meta')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('calc_meta');
            });
        }

        if (Schema::hasColumn('payouts', 'calc_meta')) {
            Schema::table('payouts', function (Blueprint $table) {
                $table->dropColumn('calc_meta');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('orders', 'calc_meta')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->json('calc_meta')->nullable();
            });
        }

        if (! Schema::hasColumn('payouts', 'calc_meta')) {
            Schema::table('payouts', function (Blueprint $table) {
                $table->json('calc_meta')->nullable();
            });
        }
    }
};
