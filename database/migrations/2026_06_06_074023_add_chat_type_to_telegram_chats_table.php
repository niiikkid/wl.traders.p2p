<?php

use App\Enums\TelegramChatParserType;
use App\Enums\TelegramChatType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('telegram_chats', function (Blueprint $table) {
            $table->string('chat_type')->nullable()->after('status');
            $table->index('chat_type');
        });

        DB::table('telegram_chats')
            ->where('parser_type', TelegramChatParserType::STANDARD_DISPUTE->value)
            ->whereNull('chat_type')
            ->update([
                'chat_type' => TelegramChatType::DISPUTE_PROCESSING->value,
            ]);

        Schema::table('telegram_chats', function (Blueprint $table) {
            $table->string('parser_type')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('telegram_chats')
            ->whereNull('parser_type')
            ->update([
                'parser_type' => TelegramChatParserType::STANDARD_DISPUTE->value,
            ]);

        Schema::table('telegram_chats', function (Blueprint $table) {
            $table->string('parser_type')->default('standard_dispute')->nullable(false)->change();
            $table->dropIndex(['chat_type']);
            $table->dropColumn('chat_type');
        });
    }
};
