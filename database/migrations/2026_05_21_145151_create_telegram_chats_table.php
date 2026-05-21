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
        Schema::create('telegram_chats', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_chat_id')->unique();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->string('username')->nullable();
            $table->string('status')->default('pending_moderation');
            $table->string('parser_type')->default('standard_dispute');
            $table->boolean('debug_enabled')->default(false);
            $table->timestamp('last_message_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('debug_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_chats');
    }
};
