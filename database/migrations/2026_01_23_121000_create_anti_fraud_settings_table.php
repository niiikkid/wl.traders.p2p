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
        Schema::create('anti_fraud_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('primary_max_pending')->nullable();
            $table->json('primary_rate_limits')->nullable();
            $table->unsignedInteger('primary_failed_limit')->nullable();
            $table->unsignedInteger('primary_block_days')->nullable();
            $table->unsignedInteger('secondary_max_pending')->nullable();
            $table->json('secondary_rate_limits')->nullable();
            $table->unsignedInteger('secondary_failed_limit')->nullable();
            $table->unsignedInteger('secondary_block_days')->nullable();
            $table->timestamps();

            $table->unique('merchant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anti_fraud_settings');
    }
};
