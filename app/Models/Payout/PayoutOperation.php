<?php

namespace App\Models\Payout;

use App\Casts\MoneyCast;
use App\Enums\PayoutOperationType;
use App\Services\Money\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $payout_id
 * @property PayoutOperationType $type
 * @property Money|null $amount
 * @property array|null $meta
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Payout $payout
 */
class PayoutOperation extends Model
{
    use HasFactory;

    protected $table = 'payout_operations';

    protected $fillable = [
        'payout_id',
        'type',
        'amount',
        'amount_currency',
        'meta',
    ];

    protected $casts = [
        'type' => PayoutOperationType::class,
        'amount' => MoneyCast::class,
        'meta' => 'array',
    ];

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class, 'payout_id');
    }
}


