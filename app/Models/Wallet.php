<?php

namespace App\Models;

use App\Casts\BaseCurrencyMoneyCast;
use App\Enums\BalanceType;
use App\Enums\TransactionType;
use App\Services\Money\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property Money $merchant_balance
 * @property Money $trust_balance
 * @property Money $reserve_balance
 * @property Money $commission_balance
 * @property Money $teamleader_balance
 * @property int $user_id
 * @property User $user
 * @property Collection<int, Invoice> $invoices
 * @property Collection<int, Transaction> $transactions
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Wallet extends Model
{
    use HasFactory;

    public const RESERVE_BALANCE = 1000;

    protected $fillable = [
        'merchant_balance',
        'trust_balance',
        'reserve_balance',
        'commission_balance',
        'teamleader_balance',
        'user_id',
    ];

    protected $casts = [
        'merchant_balance' => BaseCurrencyMoneyCast::class,
        'trust_balance' => BaseCurrencyMoneyCast::class,
        'reserve_balance' => BaseCurrencyMoneyCast::class,
        'commission_balance' => BaseCurrencyMoneyCast::class,
        'teamleader_balance' => BaseCurrencyMoneyCast::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
