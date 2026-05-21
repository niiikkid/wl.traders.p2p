<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TelegramChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TelegramChatMessage */
class TelegramChatMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'telegram_message_id' => $this->telegram_message_id,
            'message_type' => $this->message_type->value,
            'text' => $this->text,
            'caption' => $this->caption,
            'detected_uuid' => $this->detected_uuid,
            'order_id' => $this->order_id,
            'order_uuid' => $this->whenLoaded('order', fn () => $this->order?->uuid),
            'dispute_id' => $this->dispute_id,
            'status' => $this->status->value,
            'failure_reason' => $this->failure_reason,
            'is_dispute_related' => $this->is_dispute_related,
            'processed_at' => $this->processed_at?->toDateTimeString(),
            'created_at' => $this->created_at->toDateTimeString(),
            'raw_payload' => $this->raw_payload,
            'attachments' => TelegramChatMessageAttachmentResource::collection(
                $this->whenLoaded('attachments'),
            ),
        ];
    }
}
