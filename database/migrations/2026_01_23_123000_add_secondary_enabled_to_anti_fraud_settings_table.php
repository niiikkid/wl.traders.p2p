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
        Schema::table('anti_fraud_settings', function (Blueprint $table) {
            $table->boolean('secondary_enabled')->default(true)->after('primary_block_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('anti_fraud_settings', function (Blueprint $table) {
            $table->dropColumn('secondary_enabled');
        });
    }
};
