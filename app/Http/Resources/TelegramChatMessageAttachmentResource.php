<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TelegramChatMessageAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TelegramChatMessageAttachment */
class TelegramChatMessageAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $telegramChat = $this->telegramChatMessage?->telegramChat;

        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'stored_name' => $this->stored_name,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size' => $this->size,
            'download_url' => $telegramChat
                ? route('admin.telegram-chats.messages.attachments.show', [
                    'telegramChat' => $telegramChat->id,
                    'telegramChatMessage' => $this->telegram_chat_message_id,
                    'attachment' => $this->id,
                ])
                : null,
        ];
    }
}
