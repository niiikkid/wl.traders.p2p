<?php

namespace App\Models;

use App\Enums\SmsType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $sender
 * @property string $message
 * @property array $parsing_result
 * @property string $timestamp
 * @property SmsType $type
 * @property int $user_id
 * @property int $user_device_id
 * @property User $user
 * @property UserDevice $device
 * @property Order $order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SmsLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender',
        'message',
        'parsing_result',
        'timestamp',
        'type',
        'user_id',
        'user_device_id',
        'order_id',
    ];

    protected $casts = [
        'type' => SmsType::class,
        'parsing_result' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function device()
    {
        return $this->belongsTo(UserDevice::class, 'user_device_id');
    }
}
