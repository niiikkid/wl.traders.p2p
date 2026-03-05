<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property NotificationEvent $event
 * @property NotificationChannel $channel
 * @property NotificationDeliveryStatus $status
 * @property string $title
 * @property string $body
 * @property array|null $payload
 * @property string|null $error_message
 * @property \Carbon\Carbon|null $read_at
 * @property \Carbon\Carbon|null $delivered_at
 */
class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event',
        'channel',
        'status',
        'title',
        'body',
        'payload',
        'error_message',
        'read_at',
        'delivered_at',
    ];

    protected $casts = [
        'event' => NotificationEvent::class,
        'channel' => NotificationChannel::class,
        'status' => NotificationDeliveryStatus::class,
        'payload' => 'array',
        'read_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
