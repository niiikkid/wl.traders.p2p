<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TelegramChatMessageStatus;
use App\Enums\TelegramChatMessageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $telegram_chat_id
 * @property string|null $telegram_update_id
 * @property string $telegram_message_id
 * @property TelegramChatMessageType $message_type
 * @property string|null $text
 * @property string|null $caption
 * @property string|null $detected_uuid
 * @property int|null $order_id
 * @property int|null $dispute_id
 * @property TelegramChatMessageStatus $status
 * @property string|null $failure_reason
 * @property bool $is_dispute_related
 * @property array|null $raw_payload
 * @property Carbon|null $processed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property TelegramChat $telegramChat
 * @property Order|null $order
 * @property Dispute|null $dispute
 */
class TelegramChatMessage extends Model
{
    protected $fillable = [
        'telegram_chat_id',
        'telegram_update_id',
        'telegram_message_id',
        'message_type',
        'text',
        'caption',
        'detected_uuid',
        'order_id',
        'dispute_id',
        'status',
        'failure_reason',
        'is_dispute_related',
        'raw_payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'message_type' => TelegramChatMessageType::class,
            'status' => TelegramChatMessageStatus::class,
            'is_dispute_related' => 'boolean',
            'raw_payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function telegramChat(): BelongsTo
    {
        return $this->belongsTo(TelegramChat::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TelegramChatMessageAttachment::class);
    }
}
