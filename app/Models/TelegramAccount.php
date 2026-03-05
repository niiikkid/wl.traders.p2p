<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $chat_id
 * @property string|null $username
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string $link_token
 * @property bool $is_active
 * @property \Carbon\Carbon|null $linked_at
 */
class TelegramAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'chat_id',
        'username',
        'first_name',
        'last_name',
        'link_token',
        'is_active',
        'linked_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'linked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
