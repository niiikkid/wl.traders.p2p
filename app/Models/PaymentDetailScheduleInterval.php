<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $payment_detail_schedule_id
 * @property int $day_of_week ISO weekday: 1 Monday through 7 Sunday
 * @property string $starts_at Server-time HH:mm:ss
 * @property string $ends_at Server-time HH:mm:ss (exclusive boundary)
 * @property PaymentDetailSchedule $schedule
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PaymentDetailScheduleInterval extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_detail_schedule_id',
        'day_of_week',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PaymentDetailSchedule::class, 'payment_detail_schedule_id');
    }
}
