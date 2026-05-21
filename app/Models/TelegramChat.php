<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TelegramChatParserType;
use App\Enums\TelegramChatStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $telegram_chat_id
 * @property string|null $type
 * @property string|null $title
 * @property string|null $username
 * @property TelegramChatStatus $status
 * @property TelegramChatParserType $parser_type
 * @property bool $debug_enabled
 * @property Carbon|null $last_message_at
 * @property array|null $raw_payload
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TelegramChat extends Model
{
    protected $fillable = [
        'telegram_chat_id',
        'type',
        'title',
        'username',
        'status',
        'parser_type',
        'debug_enabled',
        'last_message_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'status' => TelegramChatStatus::class,
            'parser_type' => TelegramChatParserType::class,
            'debug_enabled' => 'boolean',
            'last_message_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TelegramChatMessage::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(TelegramChatMessage::class)->latestOfMany();
    }
}
