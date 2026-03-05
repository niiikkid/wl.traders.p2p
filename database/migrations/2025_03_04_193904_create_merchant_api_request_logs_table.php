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
        Schema::create('merchant_api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable();
            $table->string('amount')->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('payment_gateway', 30)->nullable();
            $table->string('payment_detail_type', 20)->nullable();
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->boolean('is_successful')->default(false);
            $table->string('error_message')->nullable();
            $table->foreignId('merchant_id')->constrained('merchants');
            $table->foreignId('order_id')->nullable()->constrained('orders');
            $table->timestamps();

            $table->index('merchant_id');
            $table->index('order_id');
            $table->index('external_id');
            $table->index('is_successful');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_api_request_logs');
    }
};
