<?php

namespace App\Models;

use App\Casts\CurrencyCast;
use App\Casts\MoneyCast;
use App\Enums\DetailType;
use App\Observers\PaymentDetailObserver;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Services\PaymentDetail\PaymentDetailScheduleAvailabilityService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $detail
 * @property DetailType $detail_type
 * @property string $initials
 * @property string|null $additional_info
 * @property bool $is_active
 * @property Money|null $daily_limit
 * @property Money $current_daily_limit
 * @property Money|null $monthly_limit
 * @property Money $current_monthly_limit
 * @property int|null $monthly_limit_reset_day
 * @property Carbon|null $monthly_limits_last_reset_on
 * @property int|null $monthly_successful_orders_limit
 * @property int $current_monthly_successful_orders_count
 * @property int|null $daily_successful_orders_limit
 * @property int $current_daily_successful_orders_count
 * @property Money $max_pending_orders_quantity
 * @property Money|null $min_order_amount
 * @property Money|null $max_order_amount
 * @property int|null $order_interval_minutes
 * @property Currency $currency
 * @property int $user_id
 * @property int|null $payment_detail_schedule_id
 * @property int $user_device_id
 * @property User $user
 * @property PaymentDetailSchedule|null $schedule
 * @property UserDevice $userDevice
 * @property Collection<int, PaymentGateway> $paymentGateways
 * @property Collection<int, Order> $orders
 * @property Collection<int, PaymentDetailAmountTierUsage> $amountTierUsages
 * @property Carbon $archived_at
 * @property Carbon $last_used_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[ObservedBy([PaymentDetailObserver::class])]
class PaymentDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'detail',
        'detail_type',
        'initials',
        'additional_info',
        'is_active',
        'daily_limit',
        'current_daily_limit',
        'monthly_limit',
        'current_monthly_limit',
        'monthly_limit_reset_day',
        'monthly_limits_last_reset_on',
        'monthly_successful_orders_limit',
        'current_monthly_successful_orders_count',
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
        'payment_detail_schedule_id',
        'user_device_id',
        'archived_at',
        'last_used_at',
    ];

    protected $casts = [
        'daily_limit' => MoneyCast::class,
        'current_daily_limit' => MoneyCast::class,
        'monthly_limit' => MoneyCast::class,
        'current_monthly_limit' => MoneyCast::class,
        'min_order_amount' => MoneyCast::class,
        'max_order_amount' => MoneyCast::class,
        'vip_min_order_amount_backup' => MoneyCast::class,
        'vip_max_order_amount_backup' => MoneyCast::class,
        'currency' => CurrencyCast::class,
        'detail_type' => DetailType::class,
        'archived_at' => 'datetime',
        'last_used_at' => 'datetime',
        'monthly_limits_last_reset_on' => 'date',
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

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PaymentDetailSchedule::class, 'payment_detail_schedule_id');
    }

    public function userDevice(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class);
    }

    public function paymentGateways(): BelongsToMany
    {
        return $this->belongsToMany(PaymentGateway::class);
    }

    public function amountTierUsages(): HasMany
    {
        return $this->hasMany(PaymentDetailAmountTierUsage::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeAvailableBySchedule(Builder $query, ?CarbonInterface $at = null): void
    {
        app(PaymentDetailScheduleAvailabilityService::class)->applyAvailableBySchedule($query, $at);
    }
}
