<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cascade_deal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cascade_deal_id')->constrained('cascade_deals')->cascadeOnDelete();
            $table->foreignId('cascade_transaction_id')->nullable()->constrained('cascade_transactions')->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('cascade_providers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('from_status')->nullable();
            $table->string('from_sub_status')->nullable();
            $table->string('to_status')->nullable();
            $table->string('to_sub_status')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['cascade_deal_id', 'created_at']);
            $table->index(['provider_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cascade_deal_events');
    }
};
