<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TelegramChat;
use App\Models\TelegramChatMessage;
use App\Models\TelegramChatMessageAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TelegramChatAttachmentController extends Controller
{
    public function show(
        TelegramChat $telegramChat,
        TelegramChatMessage $telegramChatMessage,
        TelegramChatMessageAttachment $attachment,
    ): BinaryFileResponse {
        abort_unless($telegramChatMessage->telegram_chat_id === $telegramChat->id, 404);
        abort_unless($attachment->telegram_chat_message_id === $telegramChatMessage->id, 404);

        $disk = Storage::disk('local');

        if (! $disk->exists($attachment->storage_path)) {
            abort(404);
        }

        $path = $disk->path($attachment->storage_path);
        $mime = $attachment->mime_type ?: $disk->mimeType($attachment->storage_path) ?: 'application/octet-stream';
        $downloadName = $attachment->original_name ?: $attachment->stored_name;

        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$downloadName.'"',
        ]);
    }
}
