<?php

namespace App\Models\Payout;

use App\Casts\MoneyCast;
use App\Enums\MarketEnum;
use App\Enums\PayoutMethodType;
use App\Enums\PayoutStatus;
use App\Models\CallbackLog;
use App\Models\Merchant;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Observers\PayoutObserver;
use App\Services\Money\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string|null $external_id
 * @property int $merchant_id
 * @property int|null $trader_id
 * @property int|null $payment_gateway_id
 * @property string|null $bank_name
 * @property PayoutMethodType $payout_method_type
 * @property string $requisites
 * @property string|null $initials
 *
 * @property Money $amount_fiat
 * @property Money $usdt_body
 * @property Money $total_fee
 * @property Money $trader_fee
 * @property Money $teamlead_fee
 * @property Money $service_fee
 * @property float|null $teamlead_split_from_service_percent
 * @property float|null $teamlead_split_from_trader_percent
 * @property Money $merchant_debit
 * @property Money $trader_credit
 *
 * @property MarketEnum $rate_market
 * @property Money $conversion_price
 * @property Carbon|null $rate_fixed_at
 *
 * @property PayoutStatus $status
 * @property Carbon|null $taken_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $hold_until
 * @property Carbon|null $completed_at
 * @property Carbon|null $canceled_at
 *
 * @property float|null $total_commission_rate
 * @property float|null $trader_commission_rate
 * @property float|null $teamlead_commission_rate
 *
 * @property string|null $callback_url
 * @property string|null $receipt_path
 * @property Merchant $merchant
 * @property User|null $trader
 * @property PaymentGateway $paymentGateway
 * @property \Illuminate\Database\Eloquent\Collection<int, PayoutOperation> $operations
 */
#[ObservedBy([PayoutObserver::class])]
class Payout extends Model
{
    use HasFactory;

    protected $table = 'payouts';

    protected $fillable = [
        'uuid',
        'external_id',
        'merchant_id',
        'trader_id',
        'payment_gateway_id',
        'bank_name',
        'payout_method_type',
        'requisites',
        'initials',
        'callback_url',
        'amount_fiat',
        'amount_fiat_currency',
        'usdt_body',
        'usdt_body_currency',
        'total_fee',
        'total_fee_currency',
        'trader_fee',
        'trader_fee_currency',
        'teamlead_fee',
        'teamlead_fee_currency',
        'teamlead_split_from_service_percent',
        'teamlead_split_from_trader_percent',
        'service_fee',
        'service_fee_currency',
        'merchant_debit',
        'merchant_debit_currency',
        'trader_credit',
        'trader_credit_currency',
        'total_commission_rate',
        'trader_commission_rate',
        'teamlead_commission_rate',
        'rate_market',
        'conversion_price',
        'conversion_price_currency',
        'rate_fixed_at',
        'status',
        'taken_at',
        'sent_at',
        'hold_until',
        'completed_at',
        'canceled_at',
        'expires_at',
        'receipt_path',
    ];

    protected $casts = [
        'payout_method_type' => PayoutMethodType::class,
        'status' => PayoutStatus::class,
        'rate_market' => MarketEnum::class,
        'amount_fiat' => MoneyCast::class,
        'usdt_body' => MoneyCast::class,
        'total_fee' => MoneyCast::class,
        'trader_fee' => MoneyCast::class,
        'teamlead_fee' => MoneyCast::class,
        'service_fee' => MoneyCast::class,
        'merchant_debit' => MoneyCast::class,
        'trader_credit' => MoneyCast::class,
        'conversion_price' => MoneyCast::class,
        'total_commission_rate' => 'float',
        'trader_commission_rate' => 'float',
        'teamlead_commission_rate' => 'float',
        'teamlead_split_from_service_percent' => 'float',
        'teamlead_split_from_trader_percent' => 'float',
        'rate_fixed_at' => 'datetime',
        'taken_at' => 'datetime',
        'sent_at' => 'datetime',
        'hold_until' => 'datetime',
        'completed_at' => 'datetime',
        'canceled_at' => 'datetime',
        'expires_at' => 'datetime',
        'receipt_path' => 'string',
        'callback_url' => 'string',
        'external_id' => 'string',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function trader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trader_id');
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(PayoutOperation::class, 'payout_id');
    }

    public function callbackLogs(): MorphMany
    {
        return $this->morphMany(CallbackLog::class, 'callbackable');
    }
}


