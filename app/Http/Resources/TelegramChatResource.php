<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TelegramChat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TelegramChat */
class TelegramChatResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $latestMessage = $this->whenLoaded('latestMessage');

        return [
            'id' => $this->id,
            'telegram_chat_id' => $this->telegram_chat_id,
            'type' => $this->type,
            'title' => $this->title,
            'username' => $this->username,
            'display_title' => $this->title ?: ($this->username ? '@'.$this->username : null) ?: $this->telegram_chat_id,
            'status' => $this->status->value,
            'parser_type' => $this->parser_type->value,
            'debug_enabled' => $this->debug_enabled,
            'last_message_at' => $this->last_message_at?->toDateTimeString(),
            'messages_count' => $this->whenCounted('messages', (int) $this->messages_count),
            'last_message_status' => $latestMessage?->status?->value,
            'last_failure_reason' => $latestMessage?->failure_reason,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
