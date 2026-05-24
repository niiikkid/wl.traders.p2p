<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property User $user
 * @property Collection<int, PaymentDetailScheduleInterval> $intervals
 * @property Collection<int, PaymentDetail> $paymentDetails
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PaymentDetailSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function intervals(): HasMany
    {
        return $this->hasMany(PaymentDetailScheduleInterval::class);
    }

    public function paymentDetails(): HasMany
    {
        return $this->hasMany(PaymentDetail::class);
    }
}
