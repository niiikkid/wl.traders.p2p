<?php

namespace App\Models;

use App\Casts\CurrencyCast;
use App\Casts\MoneyCast;
use App\Enums\DetailType;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $detail
 * @property DetailType $detail_type
 * @property string $initials
 * @property boolean $is_active
 * @property Money $daily_limit
 * @property Money $current_daily_limit
 * @property int|null $daily_successful_orders_limit
 * @property int $current_daily_successful_orders_count
 * @property Money $max_pending_orders_quantity
 * @property Money|null $min_order_amount
 * @property Money|null $max_order_amount
 * @property int|null $order_interval_minutes
 * @property Currency $currency
 * @property int $user_id
 * @property int $user_device_id
 * @property User $user
 * @property UserDevice $userDevice
 * @property Collection<int, PaymentGateway> $paymentGateways
 * @property Collection<int, PaymentDetailTag> $tags
 * @property Collection<int, Order> $orders
 * @property Carbon $archived_at
 * @property Carbon $last_used_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PaymentDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'detail',
        'detail_type',
        'initials',
        'is_active',
        'daily_limit',
        'current_daily_limit',
        'daily_successful_orders_limit',
        'current_daily_successful_orders_count',
        'max_pending_orders_quantity',
        'min_order_amount',
        'max_order_amount',
        'vip_min_order_amount_backup',
        'vip_max_order_amount_backup',
        'order_interval_minutes',
        'currency',
        'user_id',
        'user_device_id',
        'archived_at',
        'last_used_at',
    ];

    protected $casts = [
        'daily_limit' => MoneyCast::class,
        'current_daily_limit' => MoneyCast::class,
        'min_order_amount' => MoneyCast::class,
        'max_order_amount' => MoneyCast::class,
        'vip_min_order_amount_backup' => MoneyCast::class,
        'vip_max_order_amount_backup' => MoneyCast::class,
        'currency' => CurrencyCast::class,
        'detail_type' => DetailType::class,
        'archived_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userDevice(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class);
    }

    public function paymentGateways(): BelongsToMany
    {
        return $this->belongsToMany(PaymentGateway::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(PaymentDetailTag::class, 'payment_detail_tag_payment_detail')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
