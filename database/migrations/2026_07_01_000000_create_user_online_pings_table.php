<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_online_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Бакет онлайн-пинга веб-панели, кратный 15 секундам (epoch_seconds / 15)
            $table->unsignedBigInteger('bucket_15s');
            $table->timestamps();

            $table->unique(['user_id', 'bucket_15s'], 'uop_user_bucket_unique');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_online_pings');
    }
};
