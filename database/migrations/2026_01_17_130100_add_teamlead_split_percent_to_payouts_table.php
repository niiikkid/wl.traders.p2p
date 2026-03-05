<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->decimal('teamlead_split_from_service_percent', 5, 2)
                ->nullable()
                ->after('teamlead_split_from_trader_currency');
            $table->decimal('teamlead_split_from_trader_percent', 5, 2)
                ->nullable()
                ->after('teamlead_split_from_service_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn('teamlead_split_from_service_percent');
            $table->dropColumn('teamlead_split_from_trader_percent');
        });
    }
};
