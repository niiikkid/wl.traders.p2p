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
        Schema::dropIfExists('notification_rules');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 100);
            $table->string('currency', 10)->nullable();
            $table->json('statuses')->nullable();
            $table->string('message_scope', 32)->nullable();
            $table->string('min_amount_minor', 64)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'event']);
            $table->index(['event', 'enabled', 'currency', 'user_id'], 'nr_event_enabled_currency_user_idx');
        });
    }
};
