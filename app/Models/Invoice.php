<?php

namespace App\Models;

use App\Casts\CurrencyCast;
use App\Casts\MoneyCast;
use App\Enums\BalanceType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\NetworkEnum;
use App\Observers\InvoiceObserver;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $external_id
 * @property Money $amount
 * @property Currency $currency
 * @property string $address
 * @property NetworkEnum $network
 * @property string $tx_hash
 * @property InvoiceType $type
 * @property BalanceType $balance_type
 * @property InvoiceStatus $status
 * @property string $transaction_id
 * @property int $wallet_id
 * @property Wallet $wallet
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[ObservedBy([InvoiceObserver::class])]
class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'amount',
        'currency',
        'address',
        'network',
        'tx_hash',
        'type',
        'balance_type',
        'status',
        'transaction_id', //это внешний id, для автоматики, не относится к модели Invoice
        'wallet_id',
    ];

    protected $casts = [
        'amount' => MoneyCast::class,
        'currency' => CurrencyCast::class,
        'network' => NetworkEnum::class,
        'type' => InvoiceType::class,
        'balance_type' => BalanceType::class,
        'status' => InvoiceStatus::class,
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
