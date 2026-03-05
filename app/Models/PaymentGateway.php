<?php

namespace App\Models;

use App\Casts\CurrencyCast;
use App\Enums\DetailType;
use App\Services\Money\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $name_with_currency
 * @property string $min_limit
 * @property string $max_limit
 * @property array $sms_senders
 * @property float $trader_commission_rate_for_orders
 * @property float $total_service_commission_rate_for_orders
 * @property float $trader_commission_rate_for_payouts
 * @property float $total_service_commission_rate_for_payouts
 * @property string $is_active
 * @property bool $is_payouts_enabled
 * @property boolean $is_intrabank
 * @property int $reservation_time_for_orders
 * @property int $reservation_time_for_payouts
 * @property string $logo
 * @property array<int, DetailType> $detail_types
 * @property Currency $currency
 * @property Collection<int, PaymentDetail> $paymentDetails
 * @property Collection<int, Order> $orders
 */
class PaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'currency',
        'min_limit',
        'max_limit',
        'sms_senders',
        'trader_commission_rate_for_orders',
        'total_service_commission_rate_for_orders',
        'trader_commission_rate_for_payouts',
        'total_service_commission_rate_for_payouts',
        'is_active',
        'is_payouts_enabled',
        'is_intrabank',
        'reservation_time_for_orders',
        'reservation_time_for_payouts',
        'logo',
        'detail_types',
    ];

    protected $casts = [
        'currency' => CurrencyCast::class,
        'detail_types' => 'array',
        'sms_senders' => 'array',
        'is_payouts_enabled' => 'bool',
    ];

    public $timestamps = false;

    protected function detailTypes(): Attribute
    {
        return Attribute::make(
            get: function (string $value)  {
                $detail_types = json_decode($value, true);

                foreach ($detail_types as $key => $item) {
                    $detail_types[$key] = DetailType::from($item);
                }

                return $detail_types;
            },
        );
    }

    protected function firstName(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => ucfirst($value),
        );
    }

    protected function nameWithCurrency(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => $attributes['name'] . ' ' . strtoupper($attributes['currency']),
        );
    }

    protected function isIntrabank(): Attribute
    {
        return Attribute::make(
            set: function ($value, array $attributes) {
                // Если intrabank был включен (true), то нельзя его выключить
                if (isset($attributes['is_intrabank']) && $attributes['is_intrabank'] && !$value) {
                    return $attributes['is_intrabank'];
                }
                return $value;
            }
        );
    }

    public function paymentDetails(): BelongsToMany
    {
        return $this->belongsToMany(PaymentDetail::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', 1);
    }
}
