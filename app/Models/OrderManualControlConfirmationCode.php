<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property string $confirmation_code
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Order $order
 */
class OrderManualControlConfirmationCode extends Model
{
    use HasFactory;

    protected $table = 'order_manual_control_confirmation_codes';

    protected $fillable = [
        'order_id',
        'confirmation_code',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
