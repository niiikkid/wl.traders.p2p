<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property array $allowed_markets
 * @property array $allowed_categories
 * @property bool $notification_sound_enabled
 * @property string|null $notification_sound_track
 * @property \Carbon\Carbon|null $news_last_read_at
 * @property int $user_id
 * @property User $user
 */
class UserMeta extends Model
{
    use HasFactory;

    protected $fillable = [
        'allowed_markets',
        'allowed_categories',
        'notification_sound_enabled',
        'notification_sound_track',
        'news_last_read_at',
    ];

    protected $casts = [
        'allowed_markets' => 'array',
        'allowed_categories' => 'array',
        'notification_sound_enabled' => 'boolean',
        'news_last_read_at' => 'datetime',
    ];

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
