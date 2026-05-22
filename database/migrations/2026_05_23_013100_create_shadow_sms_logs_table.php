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
        Schema::create('shadow_sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_device_id')->constrained('user_devices')->cascadeOnDelete();
            $table->string('sender', 256)->nullable();
            $table->text('message')->nullable();
            $table->unsignedBigInteger('timestamp');
            $table->string('type');
            $table->string('filter_reason', 32)->index();
            $table->string('matched_sender', 256)->nullable();
            $table->string('matched_stop_word')->nullable();
            $table->unsignedInteger('message_length')->nullable();
            $table->timestamps();

            $table->index('sender');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shadow_sms_logs');
    }
};
