<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TelegramChatMessageStatus;
use App\Enums\TelegramChatParserType;
use App\Enums\TelegramChatStatus;
use App\Enums\TelegramChatType;
use App\Jobs\ProcessTelegramChatMessageJob;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @property TelegramChatType|null $chat_type
 * @property TelegramChatParserType|null $parser_type
 * @property bool $debug_enabled
 * @property Carbon|null $last_message_at
 * @property array|null $raw_payload
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, User> $traders
 */
class TelegramChat extends Model
{
    protected $fillable = [
        'telegram_chat_id',
        'type',
        'title',
        'username',
        'status',
        'chat_type',
        'parser_type',
        'debug_enabled',
        'last_message_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'status' => TelegramChatStatus::class,
            'chat_type' => TelegramChatType::class,
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

    public function traders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'telegram_chat_traders', 'telegram_chat_id', 'trader_id')
            ->withPivot('telegram_username')
            ->withTimestamps();
    }

    public function canProcessDisputeMessages(): bool
    {
        if (! $this->status->equals(TelegramChatStatus::ACTIVE)) {
            return false;
        }

        if ($this->chat_type?->equals(TelegramChatType::TRADER_TEAM)) {
            return false;
        }

        if ($this->chat_type !== null && ! $this->chat_type->equals(TelegramChatType::DISPUTE_PROCESSING)) {
            return false;
        }

        return $this->parser_type?->equals(TelegramChatParserType::STANDARD_DISPUTE) ?? false;
    }

    public function redispatchReceivedMessages(): int
    {
        $dispatched = 0;

        $this->messages()
            ->where('status', TelegramChatMessageStatus::RECEIVED)
            ->orderBy('id')
            ->each(function (TelegramChatMessage $message) use (&$dispatched): void {
                ProcessTelegramChatMessageJob::dispatch($message);
                $dispatched++;
            });

        return $dispatched;
    }
}
