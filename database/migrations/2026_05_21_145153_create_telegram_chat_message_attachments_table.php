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
        Schema::create('telegram_chat_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_chat_message_id');
            $table->foreign('telegram_chat_message_id', 'tg_chat_msg_att_msg_fk')
                ->references('id')
                ->on('telegram_chat_messages')
                ->cascadeOnDelete();
            $table->string('telegram_file_id');
            $table->string('telegram_file_unique_id')->nullable();
            $table->string('original_name')->nullable();
            $table->string('stored_name');
            $table->string('mime_type');
            $table->string('extension');
            $table->unsignedBigInteger('size');
            $table->string('storage_path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_chat_message_attachments');
    }
};
