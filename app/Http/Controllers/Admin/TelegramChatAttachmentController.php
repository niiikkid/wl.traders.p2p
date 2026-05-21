<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TelegramChat;
use App\Models\TelegramChatMessage;
use App\Models\TelegramChatMessageAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TelegramChatAttachmentController extends Controller
{
    public function show(
        TelegramChat $telegramChat,
        TelegramChatMessage $telegramChatMessage,
        TelegramChatMessageAttachment $attachment,
    ): StreamedResponse {
        abort_unless($telegramChatMessage->telegram_chat_id === $telegramChat->id, 404);
        abort_unless($attachment->telegram_chat_message_id === $telegramChatMessage->id, 404);

        if (! Storage::disk('local')->exists($attachment->storage_path)) {
            abort(404);
        }

        $downloadName = $attachment->original_name ?: $attachment->stored_name;

        return Storage::disk('local')->response(
            $attachment->storage_path,
            $downloadName,
            ['Content-Type' => $attachment->mime_type],
        );
    }
}
