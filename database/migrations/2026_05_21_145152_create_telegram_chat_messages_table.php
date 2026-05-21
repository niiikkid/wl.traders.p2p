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
        Schema::create('telegram_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_chat_id')->constrained('telegram_chats')->cascadeOnDelete();
            $table->string('telegram_update_id')->nullable()->unique();
            $table->string('telegram_message_id');
            $table->string('message_type')->default('unknown');
            $table->text('text')->nullable();
            $table->text('caption')->nullable();
            $table->string('detected_uuid')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('dispute_id')->nullable()->constrained('disputes')->nullOnDelete();
            $table->string('status')->default('received');
            $table->text('failure_reason')->nullable();
            $table->boolean('is_dispute_related')->default(false);
            $table->json('raw_payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['telegram_chat_id', 'telegram_message_id'], 'tg_chat_messages_chat_msg_unique');
            $table->index('order_id');
            $table->index('dispute_id');
            $table->index('is_dispute_related');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_chat_messages');
    }
};
