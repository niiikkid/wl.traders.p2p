<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $bot_token
 * @property string|null $webhook_secret
 * @property Carbon|null $webhook_set_at
 * @property string|null $webhook_last_error
 * @property array|null $webhook_metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TelegramBotSetting extends Model
{
    protected $fillable = [
        'bot_token',
        'webhook_secret',
        'webhook_set_at',
        'webhook_last_error',
        'webhook_metadata',
    ];

    protected $hidden = [
        'bot_token',
        'webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'bot_token' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'webhook_set_at' => 'datetime',
            'webhook_metadata' => 'array',
        ];
    }

    public function hasBotToken(): bool
    {
        return is_string($this->bot_token) && $this->bot_token !== '';
    }

    public function hasWebhookSecret(): bool
    {
        return is_string($this->webhook_secret) && $this->webhook_secret !== '';
    }
}
