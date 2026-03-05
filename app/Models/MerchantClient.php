<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $merchant_id
 * @property string $client_id
 * @property Carbon|null $blocked_until
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Merchant $merchant
 * @property \Illuminate\Database\Eloquent\Collection<int, Order> $orders
 */
class MerchantClient extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'client_id',
        'blocked_until',
    ];

    protected $casts = [
        'blocked_until' => 'datetime',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
