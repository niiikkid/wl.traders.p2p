<?php

namespace App\Models;

use App\Casts\BaseCurrencyMoneyCast;
use App\Enums\BalanceType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Observers\TransactionObserver;
use App\Services\Money\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property Money $amount
 * @property TransactionDirection $direction
 * @property TransactionType $type
 * @property BalanceType $balance_type
 * @property int $wallet_id
 * @property Wallet $wallet
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[ObservedBy([TransactionObserver::class])]
class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'direction',
        'type',
        'balance_type',
        'wallet_id',
    ];

    protected $casts = [
        'amount' => BaseCurrencyMoneyCast::class,
        'direction' => TransactionDirection::class,
        'type' => TransactionType::class,
        'balance_type' => BalanceType::class,
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
