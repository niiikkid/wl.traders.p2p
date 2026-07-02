<?php

namespace App\Models;

use App\Casts\BaseCurrencyMoneyCast;
use App\Enums\NetworkEnum;
use App\Services\Money\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $currency
 * @property NetworkEnum $network
 * @property string $address
 * @property string|null $label
 * @property bool $is_active
 * @property Money|null $balance_units
 * @property Carbon|null $last_checked_at
 * @property string|null $last_error
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WalletDepositAddress extends Model
{
    protected $fillable = [
        'currency',
        'network',
        'address',
        'label',
        'is_active',
        'balance_units',
        'last_checked_at',
        'last_error',
        'metadata',
    ];

    protected $casts = [
        'network' => NetworkEnum::class,
        'is_active' => 'boolean',
        'balance_units' => BaseCurrencyMoneyCast::class,
        'last_checked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(WalletDepositInvoice::class, 'deposit_address_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
