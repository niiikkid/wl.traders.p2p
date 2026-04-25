<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\BaseCurrencyMoneyCast;
use App\Casts\CurrencyCast;
use App\Casts\MoneyCast;
use App\Enums\CascadePaymentMethod;
use App\Enums\MarketEnum;
use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Каскадная сделка
 *
 * @property int $id
 * @property string $uuid UUID каскадной сделки (cascade_deal_id в API v2)
 * @property string $external_id Внешний ID от мерчанта
 *
 * @property int $merchant_id ID мерчанта
 * @property int|null $merchant_client_id ID клиента мерчанта
 * @property int|null $order_id ID внутренней сделки (если создана через InternalProvider)
 *
 * @property Money $amount Текущая сумма сделки
 * @property Money $initial_amount Изначальная сумма при создании
 * @property Currency $currency Валюта сделки
 * @property Money|null $debit Сумма, получаемая от провайдера ликвидности в USDT
 * @property Money|null $credit Сумма, выплачиваемая мерчанту в USDT
 * @property Money|null $service_profit Прибыль сервиса за операцию в USDT (debit - credit)
 * @property Money|null $usdt_amount Сумма amount после конвертации по курсу в USDT
 * @property Money|null $fee Комиссия, забираемая у мерчанта в USDT
 * @property float|null $fee_rate Комиссия в процентах, забираемая у мерчанта
 *
 * @property MarketEnum $market Рынок (bybit, binance, rapira)
 * @property Money $conversion_price Курс обмена
 * @property Carbon|null $rate_fixed_at Дата фиксации курса
 *
 * @property OrderStatus $status Статус сделки (pending, success, fail)
 * @property OrderSubStatus $sub_status Подстатус сделки
 *
 * @property int|null $selected_provider_id ID провайдера-победителя
 * @property int|null $selected_transaction_id ID победившей транзакции из CascadeTransaction
 *
 * @property CascadePaymentMethod $payment_method Метод оплаты
 * @property array|null $gateway Данные шлюза (code, name, logo_link)
 * @property array|null $details Детали платежа (type, initials, value)
 *
 * @property string|null $callback_url URL для callback'ов мерчанту
 *
 * @property Carbon|null $finished_at Дата завершения сделки
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property Merchant $merchant
 * @property MerchantClient|null $merchantClient
 * @property Order|null $order
 * @property CascadeProvider|null $selectedProvider
 */
class CascadeDeal extends Model
{
    use HasFactory;

    protected $fillable = [
        // Идентификаторы
        'uuid',
        'external_id',
        
        // Связи
        'merchant_id',
        'merchant_client_id',
        'order_id',
        
        // Суммы и экономика
        'amount',
        'initial_amount',
        'currency',
        'debit',
        'credit',
        'service_profit',
        
        // Внутренние расчеты с мерчантом
        'usdt_amount',
        'fee',
        'fee_rate',
        
        // Курс и рынок
        'market',
        'conversion_price',
        'rate_fixed_at',
        
        // Статусы
        'status',
        'sub_status',
        
        // Провайдер
        'selected_provider_id',
        'selected_transaction_id',
        
        // Детали сделки
        'payment_method',
        'gateway',
        'details',
        
        // Callback
        'callback_url',
        
        // Даты
        'finished_at',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'sub_status' => OrderSubStatus::class,
        'currency' => CurrencyCast::class,
        'market' => MarketEnum::class,
        'amount' => MoneyCast::class,
        'initial_amount' => MoneyCast::class,
        'debit' => BaseCurrencyMoneyCast::class,
        'credit' => BaseCurrencyMoneyCast::class,
        'service_profit' => BaseCurrencyMoneyCast::class,
        'usdt_amount' => BaseCurrencyMoneyCast::class,
        'fee' => BaseCurrencyMoneyCast::class,
        'fee_rate' => 'float',
        'conversion_price' => MoneyCast::class,
        'payment_method' => CascadePaymentMethod::class,
        'gateway' => 'array',
        'details' => 'array',
        'rate_fixed_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function merchantClient(): BelongsTo
    {
        return $this->belongsTo(MerchantClient::class, 'merchant_client_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function selectedProvider(): BelongsTo
    {
        return $this->belongsTo(CascadeProvider::class, 'selected_provider_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CascadeTransaction::class);
    }

    /**
     * Логи запросов к провайдерам для этой каскадной сделки
     *
     * @return HasMany
     */
    public function providerLogs(): HasMany
    {
        return $this->hasMany(CascadeProviderLog::class);
    }
}
