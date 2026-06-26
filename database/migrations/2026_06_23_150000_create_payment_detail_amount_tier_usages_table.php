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
        Schema::create('payment_detail_amount_tier_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_detail_id')
                ->constrained('payment_details')
                ->cascadeOnDelete();
            $table->string('tier');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['payment_detail_id', 'tier'], 'uniq_pd_amount_tier');
            $table->index(['payment_detail_id', 'tier', 'last_used_at'], 'idx_pd_amount_tier_last_used');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_detail_amount_tier_usages');
    }
};
