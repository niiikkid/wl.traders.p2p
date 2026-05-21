<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $telegram_chat_message_id
 * @property string $telegram_file_id
 * @property string|null $telegram_file_unique_id
 * @property string|null $original_name
 * @property string $stored_name
 * @property string $mime_type
 * @property string $extension
 * @property int $size
 * @property string $storage_path
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property TelegramChatMessage $telegramChatMessage
 */
class TelegramChatMessageAttachment extends Model
{
    protected $fillable = [
        'telegram_chat_message_id',
        'telegram_file_id',
        'telegram_file_unique_id',
        'original_name',
        'stored_name',
        'mime_type',
        'extension',
        'size',
        'storage_path',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function telegramChatMessage(): BelongsTo
    {
        return $this->belongsTo(TelegramChatMessage::class);
    }
}
