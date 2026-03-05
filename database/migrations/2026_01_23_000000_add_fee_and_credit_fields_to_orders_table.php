<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('total_fee')->nullable();
            $table->string('trader_receive')->nullable();
            $table->string('merchant_credit')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'total_fee',
                'trader_receive',
                'merchant_credit',
            ]);
        });
    }
};

