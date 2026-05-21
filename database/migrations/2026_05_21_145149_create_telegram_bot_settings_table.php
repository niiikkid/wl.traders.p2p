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
        Schema::create('telegram_bot_settings', function (Blueprint $table) {
            $table->id();
            $table->text('bot_token')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->timestamp('webhook_set_at')->nullable();
            $table->text('webhook_last_error')->nullable();
            $table->json('webhook_metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_bot_settings');
    }
};
