<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_detail_enabled_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_detail_id')->constrained('payment_details')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['payment_detail_id', 'started_at'], 'pdep_pd_started_idx');
            $table->index(['user_id', 'started_at'], 'pdep_user_started_idx');
            $table->index(['started_at', 'ended_at'], 'pdep_started_ended_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_detail_enabled_periods');
    }
};
