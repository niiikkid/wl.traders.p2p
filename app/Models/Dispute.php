<?php

namespace App\Models;

use App\Enums\DisputeCancelReasonCode;
use App\Enums\DisputeStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $receipt
 * @property string|null $bank_statement
 * @property int $order_id
 * @property int $trader_id
 * @property DisputeStatus $status
 * @property string|null $reason
 * @property DisputeCancelReasonCode|null $reason_code
 * @property Order $order
 * @property User $trader
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Dispute extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt',
        'order_id',
        'trader_id',
        'status',
        'reason',
        'reason_code',
        'bank_statement',
    ];

    protected $casts = [
        'status' => DisputeStatus::class,
        'reason_code' => DisputeCancelReasonCode::class,
    ];

    /**
     * Pending disputes first, then newest by creation time (admin/trader index tables).
     */
    public function scopeOrderedWithPendingFirst(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->orderByRaw("CASE WHEN {$table}.status = ? THEN 0 ELSE 1 END", [DisputeStatus::PENDING->value])
            ->orderByDesc("{$table}.created_at");
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function trader(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
