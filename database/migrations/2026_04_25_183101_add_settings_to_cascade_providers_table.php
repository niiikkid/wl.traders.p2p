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
        Schema::table('cascade_providers', function (Blueprint $table) {
            $table->string('base_url')->nullable()->after('priority');
            $table->string('access_token')->nullable()->after('base_url');
            $table->string('merchant_id')->nullable()->after('access_token');
            $table->string('callback_url')->nullable()->after('merchant_id');
            $table->string('currency_code', 10)->nullable()->after('callback_url');
            $table->unsignedInteger('timeout')->nullable()->after('currency_code');
            $table->boolean('verify_ssl')->default(true)->after('timeout');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cascade_providers', function (Blueprint $table) {
            $table->dropColumn([
                'base_url',
                'access_token',
                'merchant_id',
                'callback_url',
                'currency_code',
                'timeout',
                'verify_ssl',
            ]);
        });
    }
};
