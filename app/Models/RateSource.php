<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\RateSourceType;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property RateSourceType $type
 * @property string $base_currency
 * @property string $quote_currency
 * @property Money|null $rate
 * @property string|null $rate_currency
 * @property array|null $settings
 * @property bool $is_active
 * @property Carbon|null $last_refreshed_at
 * @property array|null $last_parse_attempt
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class RateSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'base_currency',
        'quote_currency',
        'rate',
        'rate_currency',
        'settings',
        'is_active',
        'last_refreshed_at',
        'last_parse_attempt',
    ];

    protected function casts(): array
    {
        return [
            'type' => RateSourceType::class,
            'rate' => MoneyCast::class,
            'settings' => 'array',
            'is_active' => 'bool',
            'last_refreshed_at' => 'datetime',
            'last_parse_attempt' => 'array',
        ];
    }

    /**
     * @param  Builder<RateSource>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<RateSource>  $query
     */
    public function scopeAutomatic(Builder $query): void
    {
        $query->whereIn('type', [RateSourceType::BYBIT->value, RateSourceType::BINANCE->value]);
    }

    /**
     * @param  Builder<RateSource>  $query
     */
    public function scopeForCurrency(Builder $query, Currency $currency): void
    {
        $query->where('quote_currency', $currency->getCode());
    }

    public function quoteCurrency(): Currency
    {
        return Currency::make($this->quote_currency);
    }

    public function pair(): string
    {
        return strtoupper($this->base_currency).'/'.strtoupper($this->quote_currency);
    }

    public function isAutomatic(): bool
    {
        return $this->type->isAutomatic();
    }
}
