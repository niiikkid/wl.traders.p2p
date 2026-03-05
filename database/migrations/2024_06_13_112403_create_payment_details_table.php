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
        Schema::create('payment_details', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('detail')->nullable();
            $table->string('detail_type')->nullable();
            $table->string('initials')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('daily_limit')->nullable();
            $table->string('current_daily_limit')->default(0);
            $table->string('currency')->nullable();
            $table->foreignId('payment_gateway_id')->nullable();
            $table->foreignId('sub_payment_gateway_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_details');
    }
};
