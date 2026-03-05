<?php

namespace App\Services\Order\Features\OrderDetailProvider\Classes;

use App\Enums\DetailType;
use App\Enums\DisputeStatus;
use App\Enums\MarketEnum;
use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Models\Merchant;
use App\Models\PaymentDetail;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Models\ValueObjects\Settings\PrimeTimeSettings;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Services\Order\Features\OrderDetailProvider\Classes\Utils\GatewayFactory;
use App\Services\Order\Features\OrderDetailProvider\Classes\Utils\TraderFactory;
use App\Services\Order\Features\OrderDetailProvider\Values\Detail;
use App\Services\Order\Features\OrderDetailProvider\Values\Gateway;
use App\Services\Order\Features\OrderDetailProvider\Values\Trader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class FindAvailablePaymentDetail
{
    protected PrimeTimeSettings $primeTimeBonus;
    protected Carbon $start;
    protected Carbon $end;
    protected Money $exchangePrice;
    protected array $inactiveGatewayIds;
    protected int $maxPendingDisputes;
    protected Money $approximateTotalProfit;

    public function __construct(
        protected Merchant        $merchant,
        protected MarketEnum      $market,
        protected Money           $amount,
        protected ?DetailType     $detailType = null,
        protected ?Currency       $currency = null,
        protected ?PaymentGateway $gateway = null,
    )
    {
        if (is_null($this->gateway) && is_null($this->currency)) {
            throw OrderException::make('Должен быть указан либо gateway, либо currency.');
        }

        $this->primeTimeBonus = services()->settings()->getPrimeTimeBonus();
        $this->start = Carbon::createFromTimeString($this->primeTimeBonus->starts);
        $this->end = Carbon::createFromTimeString($this->primeTimeBonus->ends);
        $this->exchangePrice = services()->market()->getSellPrice($this->amount->getCurrency(), $this->market);

        if (! $this->exchangePrice->greaterThanZero()) {
            throw OrderException::marketPriceUnavailable();
        }

        $this->inactiveGatewayIds = collect($this->merchant->gateway_settings)
            ->filter(fn($settings) => isset($settings['active']) && $settings['active'] === false)
            ->keys()
            ->all();
        $this->maxPendingDisputes = services()->settings()->getMaxPendingDisputes();
        $this->approximateTotalProfit = $amount->convert($this->exchangePrice, Currency::USDT());
    }

    public function get(): ?Detail
    {
        $paymentDetail = $this->queryPaymentDetails()->first();

        if (!$paymentDetail) {
            return null;
        }

        $paymentDetail->update([
            'last_used_at' => now()
        ]);

        $randomGatewayID = $paymentDetail->paymentGateways->pluck('id')->random();
        $paymentGateway = PaymentGateway::find($randomGatewayID);
        $user = User::query()
            ->with(['wallet' => function (HasOne $query) {
                $query->select(['user_id', 'trust_balance', 'currency']);
            }])
            ->with([
                'teamLeader:id,email,referral_commission_percentage,team_leader_split_from_service_percent',
            ])
            ->where('id', $paymentDetail->user_id)
            ->first();

        $gateway = (new GatewayFactory($this->merchant))->make($paymentGateway);
        $trader = (new TraderFactory())->make($user);

        return $this->makeDetail($paymentDetail, $gateway, $trader);
    }

    protected function makeDetail(PaymentDetail $paymentDetail, Gateway $gateway, Trader $trader): Detail
    {
        //Trader Commission Rate Prime Time
        $traderCommissionRate = $gateway->traderCommissionRate;

        if (now()->between($this->start, $this->end)) {
            $traderCommissionRate = round($traderCommissionRate + $this->primeTimeBonus->rate, 2);
        }

        $teamLeaderCommissionRate = $trader->teamLeaderCommissionRate;
        //Расчёт прибыли
        $profits = services()->profit()->calculateInBody(
            sourceAmount: $this->amount,
            exchangeRate: $this->exchangePrice,
            totalFeeRate: $gateway->serviceCommissionRate,
            traderFeeRate: $traderCommissionRate,
            teamLeaderFeeRate: $teamLeaderCommissionRate,
            teamLeaderServiceSplitPercent: $trader->teamLeaderSplitFromServicePercent
        );

        return new Detail(
            id: $paymentDetail->id,
            userID: $paymentDetail->user_id,
            paymentGatewayID: $gateway->id,
            userDeviceID: $paymentDetail->user_device_id,
            dailyLimit: $paymentDetail->daily_limit,
            currentDailyLimit: $paymentDetail->current_daily_limit,
            currency: $paymentDetail->currency,
            exchangePrice: $this->exchangePrice,
            totalProfit: $profits->convertedAmount,
            serviceProfit: $profits->serviceFee,
            merchantProfit: $profits->merchantCredit,
            traderProfit: $profits->traderFee,
            teamLeaderProfit: $profits->teamLeaderFee,
            traderCommissionRate: $traderCommissionRate,
            teamLeaderCommissionRate: $teamLeaderCommissionRate,
            traderPaidForOrder: $profits->traderDebit,
            gateway: $gateway,
            trader: $trader,
            amount: $this->amount,
        );
    }


    protected function queryPaymentDetails(): Builder
    {
        $userIDs = User::query()
            ->where('is_online', true)
            ->where('stop_traffic', false)
            ->whereNull('banned_at')
            ->whereHas('wallet', function ($q) {
                $q->where('trust_balance', '>=', $this->approximateTotalProfit->toUnitsInt());
            })
            ->withCount(['disputes as pending_disputes_count' => function ($q) {
                $q->where('status', DisputeStatus::PENDING);
            }])
            ->when($this->maxPendingDisputes > 0, function ($q) {
                $q->having('pending_disputes_count', '<', $this->maxPendingDisputes);
            })
            ->get()
            ->pluck('id');

        return PaymentDetail::query()
            ->with('paymentGateways:id')
            ->whereNull('archived_at')
            ->whereIn('user_id', $userIDs)
            ->where(function (Builder $query) {
                $query->whereNotNull('user_device_id')
                    ->orWhereHas('user', function (Builder $subQuery) {
                        $subQuery->where('can_work_without_device', true);
                    });
            })
            ->whereRaw('(daily_limit - current_daily_limit) >= ?', [$this->amount->toUnitsInt()])
            ->where(function (Builder $query) {
                $query->whereNull('daily_successful_orders_limit')
                    ->orWhereColumn('current_daily_successful_orders_count', '<', 'daily_successful_orders_limit');
            })
            ->where(function ($query) {
                // Проверяем, что сумма сделки больше или равна минимальной сумме сделки
                // или минимальная сумма сделки равна нулю или NULL (не установлена)
                $query->where(function ($q) {
                    $q->whereNull('min_order_amount')
                      ->orWhere('min_order_amount', 0)
                      ->orWhere('min_order_amount', '<=', $this->amount->toUnitsInt());
                });

                // Проверяем, что сумма сделки меньше или равна максимальной сумме сделки
                // или максимальная сумма сделки равна нулю или NULL (не установлена)
                $query->where(function ($q) {
                    $q->whereNull('max_order_amount')
                      ->orWhere('max_order_amount', 0)
                      ->orWhere('max_order_amount', '>=', $this->amount->toUnitsInt());
                });
            })
            ->when($this->detailType, function (Builder $query) {
                $query->where('detail_type', $this->detailType);
            })
            // Проверяем интервал между сделками
            ->where(function ($query) {
                $query->whereNull('order_interval_minutes')
                    ->orWhere('order_interval_minutes', 0)
                    ->orWhereRaw('TIMESTAMPDIFF(MINUTE, last_used_at, ?) >= order_interval_minutes', [now()])
                    ->orWhereNull('last_used_at');
            })
            // Фильтрация по уникальности суммы за последние 10 минут
            ->whereDoesntHave('orders', function ($query) {
                $query->where('status', OrderStatus::SUCCESS)
                    ->where('finished_at', '>=', now()->subMinutes(10))
                    ->where('amount', '>=', $this->amount->mul(0.97)->toUnitsInt())
                    ->where('amount', '<=', $this->amount->mul(1.03)->toUnitsInt());
            })
            // Уникальность суммы для PENDING заказов
            ->whereDoesntHave('orders', function ($query) {
                $query->where('status', OrderStatus::PENDING)
                    ->where('amount', $this->amount->toUnitsInt());
            })
            // Лимит по количеству PENDING заказов
            ->where(function ($query) {
                $query->whereNull('max_pending_orders_quantity')
                    ->orWhere('max_pending_orders_quantity', 0)
                    ->orWhereRaw('
                (
                    SELECT COUNT(*)
                    FROM orders
                    WHERE orders.payment_detail_id = payment_details.id
                        AND orders.status = ?
                ) < payment_details.max_pending_orders_quantity
            ', [OrderStatus::PENDING->value]);
            })
            //метод
            ->when(!$this->gateway, function (Builder $query) {
                $query->whereHas('paymentGateways', function ($query) {
                    $query->where('min_limit', '<=', intval($this->amount->toBeauty()))
                        ->where('max_limit', '>=', intval($this->amount->toBeauty()))
                        ->where('currency', $this->currency->getCode())
                        ->where('is_intrabank', false)
                        ->whereNotIn('payment_gateways.id', $this->inactiveGatewayIds)
                        ->where('is_active', 1);
                });
            })
            ->when($this->gateway, function (Builder $query) {
                $query->whereHas('paymentGateways', function ($query) {
                    $query->where('min_limit', '<=', intval($this->amount->toBeauty()))
                        ->where('max_limit', '>=', intval($this->amount->toBeauty()))
                        ->where('code', $this->gateway->code)
                        ->whereNotIn('payment_gateways.id', $this->inactiveGatewayIds)
                        ->where('is_active', 1);
                });
            })
            ->active()
            ->orderBy('last_used_at')
            ->when(!is_local(), function (Builder $query) {
                $query->lock('FOR UPDATE SKIP LOCKED');
            });
    }
}
