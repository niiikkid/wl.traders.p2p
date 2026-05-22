<?php

namespace App\Models;

use App\Enums\ShadowSmsLogFilterReason;
use App\Enums\SmsType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $user_device_id
 * @property string $sender
 * @property string $message
 * @property int $timestamp
 * @property SmsType $type
 * @property ShadowSmsLogFilterReason $filter_reason
 * @property string|null $matched_sender
 * @property string|null $matched_stop_word
 * @property int|null $message_length
 * @property User $user
 * @property UserDevice $device
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ShadowSmsLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_device_id',
        'sender',
        'message',
        'timestamp',
        'type',
        'filter_reason',
        'matched_sender',
        'matched_stop_word',
        'message_length',
    ];

    protected $casts = [
        'timestamp' => 'integer',
        'type' => SmsType::class,
        'filter_reason' => ShadowSmsLogFilterReason::class,
        'message_length' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'user_device_id');
    }
}
