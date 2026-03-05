<?php

namespace App\Models;

use App\Casts\BaseCurrencyMoneyCast;
use App\Casts\CurrencyCast;
use App\Casts\MoneyCast;
use App\Enums\MarketEnum;
use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Observers\OrderObserver;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $external_id
 * @property Money $base_amount
 * @property Money $amount
 * @property Money $total_profit
 * @property Money $trader_profit
 * @property Money $team_leader_profit
 * @property Money $merchant_profit
 * @property Money $service_profit
 * @property Money|null $total_fee
 * @property Money|null $trader_receive
 * @property Money|null $merchant_credit
 * @property Money $trader_paid_for_order
 * @property float|null $team_leader_split_from_service_percent
 * @property Currency $currency
 * @property MarketEnum $market
 * @property Money $conversion_price
 * @property Carbon|null $rate_fixed_at
 * @property float $trader_commission_rate
 * @property float $team_leader_commission_rate
 * @property float $total_service_commission_rate
 * @property OrderStatus $status
 * @property OrderSubStatus $sub_status
 * @property string $status_name
 * @property string $callback_url
 * @property string $success_url
 * @property string $fail_url
 * @property array $amount_updates_history
 * @property boolean $is_h2h
 * @property int $payment_gateway_id
 * @property int $payment_detail_id
 * @property int $trader_id
 * @property int $team_leader_id
 * @property int $merchant_id
 * @property int|null $merchant_client_id
 * @property PaymentGateway $paymentGateway
 * @property PaymentDetail $paymentDetail
 * @property User $trader
 * @property User $teamLeader
 * @property Merchant $merchant
 * @property MerchantClient|null $merchantClient
 * @property SmsLog $smsLog
 * @property Dispute $dispute
 * @property Carbon $finished_at
 * @property Carbon $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Collection<int, CallbackLog> $callbackLogs
 */
#[ObservedBy([OrderObserver::class])]
class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'external_id',
        'base_amount',//Сумма при создании сделки
        'amount', // Сумма (мог измениться через updateAmount)
        'total_profit', // Тело (totalProfit)
        'trader_profit', // Комиссия трейдера (traderProfit)
        'team_leader_profit', // Комиссия тимлида / Зачислено тимлиду (teamLeaderProfit)
        'merchant_profit', // Получит мерчант (merchantProfit)
        'service_profit', // Комиссия сервиса (serviceProfit)
        'total_fee', // Комиссия всего (totalFee)
        'trader_paid_for_order', // Списано у трейдера (traderDebit / traderPaidForOrder)
        'team_leader_split_from_service_percent', // Сплит тимлида: платит сервис, %
        'currency',
        'market',
        'conversion_price', // Курс (exchangeRate)
        'rate_fixed_at',
        'trader_commission_rate', // Комиссия трейдера, %
        'team_leader_commission_rate', // Комиссия тимлида, %
        'total_service_commission_rate', // Комиссия всего, %
        'status',
        'sub_status',
        'callback_url',
        'success_url',
        'fail_url',
        'amount_updates_history',
        'is_h2h',
        'payment_gateway_id',
        'payment_detail_id',
        'trader_id',
        'team_leader_id',
        'merchant_id',
        'merchant_client_id',
        'expires_at',
        'finished_at',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'sub_status' => OrderSubStatus::class,
        'expires_at' => 'datetime',
        'finished_at' => 'datetime',
        'currency' => CurrencyCast::class,
        'market' => MarketEnum::class,
        'base_amount' => MoneyCast::class,
        'amount' => MoneyCast::class,
        'total_profit' => BaseCurrencyMoneyCast::class,
        'trader_profit' => BaseCurrencyMoneyCast::class,
        'team_leader_profit' => BaseCurrencyMoneyCast::class,
        'merchant_profit' => BaseCurrencyMoneyCast::class,
        'service_profit' => BaseCurrencyMoneyCast::class,
        'total_fee' => BaseCurrencyMoneyCast::class,
        'trader_receive' => BaseCurrencyMoneyCast::class,
        'merchant_credit' => BaseCurrencyMoneyCast::class,
        'trader_paid_for_order' => BaseCurrencyMoneyCast::class,
        'team_leader_split_from_service_percent' => 'float',
        'conversion_price' => MoneyCast::class,
        'rate_fixed_at' => 'datetime',
        'amount_updates_history' => 'array',
    ];

    protected static function booted()
    {
        static::addGlobalScope(function (Builder $builder) {
            $builder->whereNotNull('payment_detail_id');
        });
    }

    protected function statusName(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => trans("order.status.{$attributes['status']}"),
        );
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function paymentDetail(): BelongsTo
    {
        return $this->belongsTo(PaymentDetail::class);
    }

    public function trader(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    public function smsLog(): HasOne
    {
        return $this->hasOne(SmsLog::class);
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(Dispute::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function merchantClient(): BelongsTo
    {
        return $this->belongsTo(MerchantClient::class);
    }

    /**
     * Получить логи колбеков для заказа.
     *
     * @return MorphMany
     */
    public function callbackLogs(): MorphMany
    {
        return $this->morphMany(CallbackLog::class, 'callbackable');
    }
}
