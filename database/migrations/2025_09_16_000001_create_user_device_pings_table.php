<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_device_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_device_id')->constrained('user_devices')->cascadeOnDelete();
            // Бакет пинга, кратный 5 секундам (epoch_seconds / 5)
            $table->unsignedBigInteger('bucket_5s');
            $table->timestamps();

            $table->unique(['user_device_id', 'bucket_5s'], 'udp_device_bucket_unique');
            $table->index(['user_device_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_device_pings');
    }
};


