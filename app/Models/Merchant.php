<?php

namespace App\Models;

use App\Enums\MarketEnum;
use App\Services\Money\Currency;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $description
 * @property string $domain
 * @property string $callback_url
 * @property string|null $payout_callback_url
 * @property boolean $active
 * @property int $user_id
 * @property User $user
 * @property Collection<int, Order> $orders
 * @property Collection<int, Category> $categories
 * @property Collection<int, User> $supports Саппорты, имеющие доступ к этому мерчанту
 * @property AntiFraudSetting|null $antiFraudSetting
 * @property array $settings
 * @property array $gateway_settings
 * @property int|null $max_order_wait_time
 * @property int|null $min_order_amounts
 * @property Carbon $validated_at
 * @property Carbon $banned_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Merchant extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'domain',
        'callback_url',
        'payout_callback_url',
        'token',
        'user_id',
        'active',
        'settings',
        'gateway_settings',
        'max_order_wait_time',
        'min_order_amounts',
        'validated_at',
        'banned_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'gateway_settings' => 'array',
        'min_order_amounts' => 'array',
        'validated_at' => 'datetime',
        'banned_at' => 'datetime',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function antiFraudSetting(): HasOne
    {
        return $this->hasOne(AntiFraudSetting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Получить категории, к которым принадлежит мерчант.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }
    
    /**
     * Получить саппортов, которые имеют доступ к этому мерчанту
     */
    public function supports(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'merchant_supports', 'merchant_id', 'support_id')
            ->withTimestamps();
    }

    /**
     * Получить карту GEO (валюта => маркет).
     */
    public function getGeoMap(): array
    {
        return $this->settings['geos'] ?? [];
    }

    /**
     * Сохранить карту GEO в настройках мерчанта.
     */
    public function setGeoMap(array $geoMap): void
    {
        $settings = $this->settings ?? [];
        $settings['geos'] = $geoMap;
        $this->settings = $settings;
    }

    /**
     * Получить маркет для конкретной валюты GEO.
     */
    public function getGeoMarket(Currency $currency): ?MarketEnum
    {
        $geoMap = $this->getGeoMap();
        $market = $geoMap[$currency->getCode()] ?? $geoMap[strtolower($currency->getCode())] ?? null;

        return $market ? MarketEnum::tryFrom($market) : null;
    }
}
