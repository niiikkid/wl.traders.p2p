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
            $table->string('bank_name', 30)->nullable()->after('payment_gateway_id');
            $table->unsignedBigInteger('payment_gateway_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn('bank_name');
            $table->unsignedBigInteger('payment_gateway_id')->nullable(false)->change();
        });
    }
};
