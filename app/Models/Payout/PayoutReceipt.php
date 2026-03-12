<?php

namespace App\Models\Payout;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $payout_id
 * @property string $path
 * @property int $sort_order
 * @property Payout $payout
 */
class PayoutReceipt extends Model
{
    use HasFactory;

    protected $table = 'payout_receipts';

    protected $fillable = [
        'payout_id',
        'path',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class, 'payout_id');
    }
}

