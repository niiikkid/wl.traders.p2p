<?php

namespace App\Models;

use App\Casts\BaseCurrencyMoneyCast;
use App\Casts\CurrencyCast;
use App\Enums\BalanceType;
use App\Enums\NetworkEnum;
use App\Enums\WalletDepositInvoiceStatus;
use App\Enums\WalletDepositMatchType;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $wallet_id
 * @property Wallet $wallet
 * @property BalanceType $balance_type
 * @property int|null $deposit_address_id
 * @property WalletDepositAddress|null $depositAddress
 * @property string $address
 * @property Currency $currency
 * @property NetworkEnum $network
 * @property Money $amount
 * @property Money|null $amount_received
 * @property WalletDepositInvoiceStatus $status
 * @property string|null $txid
 * @property int $confirmations
 * @property WalletDepositMatchType|null $match_type
 * @property Carbon|null $matched_at
 * @property int|null $resolved_by_user_id
 * @property User|null $resolvedBy
 * @property string|null $resolution_note
 * @property Carbon $expires_at
 * @property Carbon|null $poll_until_at
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $finalized_at
 * @property string|null $error_message
 * @property string|null $qr_disk
 * @property string|null $qr_path
 * @property int|null $settled_invoice_id
 * @property Invoice|null $settledInvoice
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WalletDepositInvoice extends Model
{
    protected $fillable = [
        'uuid',
        'wallet_id',
        'balance_type',
        'deposit_address_id',
        'address',
        'currency',
        'network',
        'amount',
        'amount_received',
        'status',
        'txid',
        'confirmations',
        'match_type',
        'matched_at',
        'resolved_by_user_id',
        'resolution_note',
        'expires_at',
        'poll_until_at',
        'last_checked_at',
        'finalized_at',
        'error_message',
        'qr_disk',
        'qr_path',
        'settled_invoice_id',
    ];

    protected $casts = [
        'balance_type' => BalanceType::class,
        'currency' => CurrencyCast::class,
        'network' => NetworkEnum::class,
        'amount' => BaseCurrencyMoneyCast::class,
        'amount_received' => BaseCurrencyMoneyCast::class,
        'status' => WalletDepositInvoiceStatus::class,
        'match_type' => WalletDepositMatchType::class,
        'confirmations' => 'integer',
        'matched_at' => 'datetime',
        'expires_at' => 'datetime',
        'poll_until_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (WalletDepositInvoice $invoice): void {
            if (empty($invoice->uuid)) {
                $invoice->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function depositAddress(): BelongsTo
    {
        return $this->belongsTo(WalletDepositAddress::class, 'deposit_address_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function settledInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'settled_invoice_id');
    }

    public function scopeOpenForPolling(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [
                WalletDepositInvoiceStatus::PENDING->value,
                WalletDepositInvoiceStatus::PROCESSING->value,
            ])
            ->where(function (Builder $query): void {
                $query->whereNull('poll_until_at')
                    ->orWhere('poll_until_at', '>', now());
            });
    }
}
