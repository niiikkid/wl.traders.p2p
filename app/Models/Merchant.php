<?php

namespace App\Models;

use App\Enums\DetailType;
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

    public function getMerchantApiRateSettingsMap(): array
    {
        return $this->settings['merchant_api_rates'] ?? [];
    }

    public function getMerchantApiRateSetting(Currency $currency): ?array
    {
        $settings = $this->getMerchantApiRateSettingsMap();
        $code = strtolower($currency->getCode());

        $config = $settings[$code] ?? $settings[strtoupper($code)] ?? null;

        if (! is_array($config)) {
            return null;
        }

        $referenceRate = isset($config['order_reference_rate'])
            ? (float) $config['order_reference_rate']
            : (isset($config['reference_rate']) ? (float) $config['reference_rate'] : null);
        $payoutReferenceRate = isset($config['payout_reference_rate'])
            ? (float) $config['payout_reference_rate']
            : (isset($config['reference_rate']) ? (float) $config['reference_rate'] : null);
        $maxDeviationPercent = isset($config['max_deviation_percent']) ? (float) $config['max_deviation_percent'] : null;

        if ($referenceRate === null || $payoutReferenceRate === null || $maxDeviationPercent === null) {
            return null;
        }

        return [
            'order_reference_rate' => $referenceRate,
            'payout_reference_rate' => $payoutReferenceRate,
            'max_deviation_percent' => $maxDeviationPercent,
        ];
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

    /**
     * Получить нормализованные настройки комиссий мерчанта по валюте и типу реквизита.
     *
     * @return array<int, array{
     *     currency: string,
     *     detail_type: string,
     *     trader_commission_rate_for_orders: ?float,
     *     total_service_commission_rate_for_orders: ?float,
     *     use_flexible_trader_commission_for_orders: bool,
     *     trader_commission_tiers_for_orders: array<int, array{from: float, to: float, rate: float}>,
     *     total_service_commission_tiers_for_orders: array<int, array{from: float, to: float, rate: float}>
     * }>
     */
    public function getCommissionSettings(): array
    {
        $settings = $this->settings ?? [];
        $rawCommissionSettings = $settings['commission_settings'] ?? [];

        if (! is_array($rawCommissionSettings)) {
            return [];
        }

        $items = array_is_list($rawCommissionSettings)
            ? $rawCommissionSettings
            : array_values($rawCommissionSettings);

        return collect($items)
            ->map(function ($item) {
                if (! is_array($item)) {
                    return null;
                }

                $currency = strtolower((string) ($item['currency'] ?? ''));
                $detailType = (string) ($item['detail_type'] ?? '');

                if ($currency === '' || $detailType === '') {
                    return null;
                }

                return [
                    'currency' => $currency,
                    'detail_type' => $detailType,
                    'trader_commission_rate_for_orders' => $this->normalizeNullableRate(
                        $item['trader_commission_rate_for_orders'] ?? null
                    ),
                    'total_service_commission_rate_for_orders' => $this->normalizeNullableRate(
                        $item['total_service_commission_rate_for_orders'] ?? null
                    ),
                    'use_flexible_trader_commission_for_orders' => (bool) ($item['use_flexible_trader_commission_for_orders'] ?? false),
                    'trader_commission_tiers_for_orders' => $this->normalizeCommissionTiers(
                        $item['trader_commission_tiers_for_orders'] ?? []
                    ),
                    'total_service_commission_tiers_for_orders' => $this->normalizeCommissionTiers(
                        $item['total_service_commission_tiers_for_orders'] ?? []
                    ),
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }

    public function setCommissionSettings(array $items): void
    {
        $normalized = collect($items)
            ->map(function (array $item): ?array {
                $currency = strtolower((string) ($item['currency'] ?? ''));
                $detailType = (string) ($item['detail_type'] ?? '');

                if ($currency === '' || $detailType === '') {
                    return null;
                }

                return [
                    'currency' => $currency,
                    'detail_type' => $detailType,
                    'trader_commission_rate_for_orders' => $this->normalizeNullableRate(
                        $item['trader_commission_rate_for_orders'] ?? null
                    ),
                    'total_service_commission_rate_for_orders' => $this->normalizeNullableRate(
                        $item['total_service_commission_rate_for_orders'] ?? null
                    ),
                    'use_flexible_trader_commission_for_orders' => (bool) ($item['use_flexible_trader_commission_for_orders'] ?? false),
                    'trader_commission_tiers_for_orders' => $this->normalizeCommissionTiers(
                        $item['trader_commission_tiers_for_orders'] ?? []
                    ),
                    'total_service_commission_tiers_for_orders' => $this->normalizeCommissionTiers(
                        $item['total_service_commission_tiers_for_orders'] ?? []
                    ),
                ];
            })
            ->filter()
            ->keyBy(fn (array $item) => $item['currency'] . '|' . $item['detail_type'])
            ->toArray();

        $settings = $this->settings ?? [];
        $settings['commission_settings'] = $normalized;
        $this->settings = $settings;
    }

    public function getCommissionSettingForPair(Currency $currency, DetailType $detailType): ?array
    {
        $key = strtolower($currency->getCode()) . '|' . $detailType->value;

        foreach ($this->getCommissionSettings() as $setting) {
            if (($setting['currency'] . '|' . $setting['detail_type']) === $key) {
                return $setting;
            }
        }

        return null;
    }

    private function normalizeCommissionTiers(array $tiers): array
    {
        return collect($tiers)
            ->filter(fn ($tier) => is_array($tier))
            ->map(fn (array $tier) => [
                'from' => (float) ($tier['from'] ?? 0),
                'to' => (float) ($tier['to'] ?? 0),
                'rate' => (float) ($tier['rate'] ?? 0),
            ])
            ->values()
            ->toArray();
    }

    private function normalizeNullableRate(mixed $rate): ?float
    {
        if ($rate === null || $rate === '') {
            return null;
        }

        return (float) $rate;
    }
}
