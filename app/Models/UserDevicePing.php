<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_device_id
 * @property int $bucket_5s
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class UserDevicePing extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_device_id',
        'bucket_5s',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'user_device_id');
    }

    public static function toBucket5s(CarbonInterface $time): int
    {
        return intdiv($time->getTimestamp(), 5);
    }
}


