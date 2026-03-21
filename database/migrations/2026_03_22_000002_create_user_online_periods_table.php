<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_online_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at');
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
            $table->index(['user_id', 'ended_at']);
            $table->index(['started_at', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_online_periods');
    }
};
