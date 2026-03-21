<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $payment_detail_id
 * @property int $user_id
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PaymentDetailEnabledPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_detail_id',
        'user_id',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function paymentDetail(): BelongsTo
    {
        return $this->belongsTo(PaymentDetail::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
