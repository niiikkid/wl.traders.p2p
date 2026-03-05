<?php

namespace App\Models;

use App\Casts\CurrencyCast;
use App\Casts\MoneyCast;
use App\Enums\BalanceType;
use App\Enums\FundsOnHoldStatus;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property Money $amount
 * @property Currency $currency
 * @property int $source_wallet_id
 * @property Wallet $sourceWallet
 * @property BalanceType $source_wallet_balance_type
 * @property int $destination_wallet_id
 * @property Wallet $destinationWallet
 * @property BalanceType $destination_wallet_balance_type
 * @property FundsOnHoldStatus $status
 * @property Model $holdable
 * @property Carbon $hold_until
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class FundsOnHold extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'currency',
        'source_wallet_id',
        'source_wallet_balance_type',
        'destination_wallet_id',
        'destination_wallet_balance_type',
        'hold_until',
        'holdable_id',
        'holdable_type',
        'status',
    ];

    protected $casts = [
        'amount' => MoneyCast::class,
        'currency' => CurrencyCast::class,
        'source_wallet_balance_type' => BalanceType::class,
        'destination_wallet_balance_type' => BalanceType::class,
        'hold_until' => 'datetime',
        'status' => FundsOnHoldStatus::class,
    ];

    public function holdable(): MorphTo
    {
        return $this->morphTo();
    }

    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'source_wallet_id');
    }

    public function destinationWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'destination_wallet_id');
    }
}
