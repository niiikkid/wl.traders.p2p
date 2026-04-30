<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cascade_merchant_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cascade_deal_id')->nullable()->constrained('cascade_deals')->cascadeOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            $table->string('operation');
            $table->string('direction')->default('incoming');
            $table->string('method');
            $table->string('url');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedInteger('status_code')->nullable();
            $table->float('execution_time')->nullable();
            $table->boolean('is_successful')->default(false);
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('cascade_deal_id');
            $table->index('merchant_id');
            $table->index(['cascade_deal_id', 'merchant_id']);
            $table->index('operation');
            $table->index('direction');
            $table->index('is_successful');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cascade_merchant_logs');
    }
};
