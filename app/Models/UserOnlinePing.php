<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $bucket_15s
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property User $user
 */
class UserOnlinePing extends Model
{
    use HasFactory;

    /**
     * Шаг хранения онлайн-пингов веб-панели в секундах.
     * Жёсткое правило: данные хранятся не чаще, чем раз в 15 секунд,
     * независимо от того, как часто фронтенд присылает пинг.
     */
    public const STEP_SECONDS = 15;

    protected $fillable = [
        'user_id',
        'bucket_15s',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function toBucket15s(CarbonInterface $time): int
    {
        return intdiv($time->getTimestamp(), self::STEP_SECONDS);
    }
}
