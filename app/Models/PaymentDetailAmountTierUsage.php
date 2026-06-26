<?php

namespace App\Models;

use App\Enums\UahAmountTier;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks, per payment detail, the last time an order of a given amount tier was issued.
 * Used exclusively to build a per-amount fairness queue for UAH payment details.
 *
 * @property int $id
 * @property int $payment_detail_id
 * @property UahAmountTier $tier
 * @property Carbon|null $last_used_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property PaymentDetail $paymentDetail
 */
class PaymentDetailAmountTierUsage extends Model
{
    protected $fillable = [
        'payment_detail_id',
        'tier',
        'last_used_at',
    ];

    protected $casts = [
        'tier' => UahAmountTier::class,
        'last_used_at' => 'datetime',
    ];

    public function paymentDetail(): BelongsTo
    {
        return $this->belongsTo(PaymentDetail::class);
    }
}
