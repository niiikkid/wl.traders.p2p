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
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('telegrams');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->longText('message');
            $table->unsignedInteger('recipients_count');
            $table->unsignedInteger('delivered_count');
            $table->timestamps();
        });

        Schema::create('telegrams', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_id')->nullable();
            $table->string('member_status')->nullable();
            $table->foreignId('user_id')->nullable();
        });
    }
};
