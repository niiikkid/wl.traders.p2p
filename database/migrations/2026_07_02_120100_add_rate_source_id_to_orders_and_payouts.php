<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('rate_source_id')->nullable()->after('market');
            $table->index('rate_source_id');
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->unsignedBigInteger('rate_source_id')->nullable()->after('rate_market');
            $table->index('rate_source_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['rate_source_id']);
            $table->dropColumn('rate_source_id');
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->dropIndex(['rate_source_id']);
            $table->dropColumn('rate_source_id');
        });
    }
};
