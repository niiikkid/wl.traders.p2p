<?php

namespace App\Services\Order\Features\OrderDetailProvider\Classes;

use App\Enums\DetailType;
use App\Enums\DisputeStatus;
use App\Enums\MarketEnum;
use App\Enums\OrderStatus;
use App\Enums\TeamLeaderInsuranceMode;
use App\Enums\UahAmountTier;
use App\Exceptions\OrderException;
use App\Models\Merchant;
use App\Models\PaymentDetail;
use App\Models\PaymentDetailAmountTierUsage;
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
use App\Services\User\TeamLeaderInsuranceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class FindAvailablePaymentDetail
{
    protected PrimeTimeSettings $primeTimeBonus;

    protected Carbon $start;

    protected Carbon $end;

    protected Money $exchangePrice;

    protected MarketEnum $exchangeMarket;

    protected int $maxPendingDisputes;

    protected Money $approximateTotalProfit;

    protected bool $useUahAmountTierQueue = false;

    protected ?UahAmountTier $uahAmountTier = null;

    public function __construct(
        protected Merchant $merchant,
        protected MarketEnum $market,
        protected Money $amount,
        protected ?DetailType $detailType = null,
        protected ?Currency $currency = null,
        protected ?PaymentGateway $gateway = null,
        protected ?Money $forcedExchangePrice = null,
        protected ?MarketEnum $forcedMarket = null,
        protected ?int $rateSourceId = null,
    ) {
        if (is_null($this->gateway) && is_null($this->currency)) {
            throw OrderException::make('Должен быть указан либо gateway, либо currency.');
        }

        $this->primeTimeBonus = services()->settings()->getPrimeTimeBonus();
        $this->start = Carbon::createFromTimeString($this->primeTimeBonus->starts);
        $this->end = Carbon::createFromTimeString($this->primeTimeBonus->ends);
        if ($this->forcedExchangePrice && $this->forcedExchangePrice->greaterThanZero()) {
            $this->exchangePrice = $this->forcedExchangePrice;
            $this->exchangeMarket = $this->forcedMarket ?? $this->market;
        } else {
            if ($this->market->equals(MarketEnum::MERCHANT_API)) {
                throw OrderException::marketPriceUnavailable();
            }

            $resolvedPrice = services()->market()->getResolvedSellPrice($this->amount->getCurrency(), $this->market);

            $this->exchangePrice = $resolvedPrice->price;
            $this->exchangeMarket = $resolvedPrice->market;
        }

        if (! $this->exchangePrice->greaterThanZero()) {
            throw OrderException::marketPriceUnavailable();
        }

        $this->maxPendingDisputes = services()->settings()->getMaxPendingDisputes();
        $this->approximateTotalProfit = $amount->convert($this->exchangePrice, Currency::USDT());

        // Для гривны очередь "кому давно не выдавали" строится отдельно по диапазону суммы,
        // чтобы поток мелких сумм не вытеснял справедливую выдачу крупных. Остальные валюты не меняются.
        if ($this->amount->getCurrency()->getCode() === Currency::UAH()->getCode()) {
            $this->useUahAmountTierQueue = true;
            $this->uahAmountTier = UahAmountTier::fromAmount((int) $this->amount->toBeauty());
        }
    }

    public function get(): ?Detail
    {
        $paymentDetail = $this->queryPaymentDetails()->first();

        if (! $paymentDetail) {
            $this->logWhenBlockedByTeamLeaderReserveThreshold();

            return null;
        }

        $paymentDetail->update([
            'last_used_at' => now(),
        ]);

        if ($this->useUahAmountTierQueue) {
            // Двигаем очередь именно того диапазона, по которому выдали сделку.
            PaymentDetailAmountTierUsage::updateOrCreate(
                [
                    'payment_detail_id' => $paymentDetail->id,
                    'tier' => $this->uahAmountTier->value,
                ],
                [
                    'last_used_at' => now(),
                ]
            );
        }

        $randomGatewayID = $paymentDetail->paymentGateways->pluck('id')->random();
        $paymentGateway = PaymentGateway::find($randomGatewayID);
        $user = User::query()
            ->with(['wallet' => function (HasOne $query) {
                $query->select(['user_id', 'trust_balance', 'currency']);
            }])
            ->with([
                'teamLeader:id,email,referral_commission_percentage,team_leader_split_from_service_percent,team_leader_extended_access_enabled,team_leader_flexible_trader_commission_enabled,team_leader_flexible_trader_commission_min,team_leader_flexible_trader_commission_max',
            ])
            ->where('id', $paymentDetail->user_id)
            ->first();

        $gateway = (new GatewayFactory($this->merchant, $this->amount, $paymentDetail->detail_type))->make($paymentGateway);
        $trader = (new TraderFactory)->make($user);

        return $this->makeDetail($paymentDetail, $gateway, $trader);
    }

    protected function makeDetail(PaymentDetail $paymentDetail, Gateway $gateway, Trader $trader): Detail
    {
        // Trader Commission Rate Prime Time
        $traderCommissionRate = $gateway->traderCommissionRate;

        if (now()->between($this->start, $this->end)) {
            $traderCommissionRate = round($traderCommissionRate + $this->primeTimeBonus->rate, 2);
        }

        $teamLeaderCommissionRate = $trader->teamLeaderCommissionRate;
        // Расчёт прибыли
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
            market: $this->exchangeMarket,
            rateSourceId: $this->rateSourceId,
        );
    }

    protected function queryPaymentDetails(): Builder
    {
        $userIDs = User::query()
            ->where('is_online', true)
            ->where('stop_traffic', false)
            ->whereNull('banned_at')
            ->whereNull('archived_at')
            ->tap(fn (Builder $query) => app(TeamLeaderInsuranceService::class)->constrainEligibleTradersForOrderIssuing($query))
            ->whereHas('wallet', function ($q) {
                $requiredUnits = $this->approximateTotalProfit->toUnitsInt();
                $sharedMode = TeamLeaderInsuranceMode::TeamLeaderReserve->value;

                $q->where(function (Builder $walletQuery) use ($requiredUnits, $sharedMode) {
                    $walletQuery
                        ->where('trust_balance', '>=', $requiredUnits)
                        ->orWhere(function (Builder $sharedReserveQuery) use ($requiredUnits, $sharedMode) {
                            $sharedReserveQuery
                                ->whereHas('user', function (Builder $userQuery) use ($sharedMode) {
                                    $userQuery
                                        ->whereNotNull('team_leader_id')
                                        ->whereHas('teamLeader', function (Builder $teamLeaderQuery) use ($sharedMode) {
                                            $teamLeaderQuery->where('team_leader_insurance_mode', $sharedMode);
                                        });
                                })
                                ->whereRaw(
                                    'CAST(trust_balance AS DECIMAL(65, 0)) + COALESCE((
                                        SELECT CAST(team_leader_wallets.reserve_balance AS DECIMAL(65, 0))
                                        FROM users AS traders
                                        INNER JOIN wallets AS team_leader_wallets ON team_leader_wallets.user_id = traders.team_leader_id
                                        WHERE traders.id = wallets.user_id
                                    ), 0) >= ?',
                                    [$requiredUnits]
                                );
                        });
                });
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
            ->where(function (Builder $query) {
                $query->whereNull('daily_limit')
                    ->orWhereRaw('(daily_limit - current_daily_limit) >= ?', [$this->amount->toUnitsInt()]);
            })
            ->where(function (Builder $query) {
                $query->whereNull('monthly_limit')
                    ->orWhere('monthly_limit', 0)
                    ->orWhereNull('monthly_limit_reset_day')
                    ->orWhereRaw('(monthly_limit - current_monthly_limit) >= ?', [$this->amount->toUnitsInt()]);
            })
            ->where(function (Builder $query) {
                $query->whereNull('daily_successful_orders_limit')
                    ->orWhereColumn('current_daily_successful_orders_count', '<', 'daily_successful_orders_limit');
            })
            ->where(function (Builder $query) {
                $query->whereNull('monthly_successful_orders_limit')
                    ->orWhereNull('monthly_limit_reset_day')
                    ->orWhereColumn('current_monthly_successful_orders_count', '<', 'monthly_successful_orders_limit');
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
            // Фильтрация по уникальности суммы за последние 5 минут
            ->whereDoesntHave('orders', function ($query) {
                $query->where('status', OrderStatus::SUCCESS)
                    ->where('finished_at', '>=', now()->subMinutes(5))
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
            // метод
            ->when(! $this->gateway, function (Builder $query) {
                $query->whereHas('paymentGateways', function ($query) {
                    $query->where('min_limit', '<=', intval($this->amount->toBeauty()))
                        ->where('max_limit', '>=', intval($this->amount->toBeauty()))
                        ->where('currency', $this->currency->getCode())
                        ->where('is_intrabank', false)
                        ->where('is_active', 1);
                });
            })
            ->when($this->gateway, function (Builder $query) {
                $query->whereHas('paymentGateways', function ($query) {
                    $query->where('min_limit', '<=', intval($this->amount->toBeauty()))
                        ->where('max_limit', '>=', intval($this->amount->toBeauty()))
                        ->where('code', $this->gateway->code)
                        ->where('is_active', 1);
                });
            })
            ->active()
            ->availableBySchedule()
            ->when($this->useUahAmountTierQueue, function (Builder $query) {
                // Очередь по диапазону суммы: реквизит, которому дольше всего не выдавали
                // сделку в этом диапазоне, идёт первым. Реквизиты без выдачи в этом
                // диапазоне (NULL) сортируются первыми — как и при пустом last_used_at.
                $query->orderByRaw(
                    '(SELECT pdatu.last_used_at
                        FROM payment_detail_amount_tier_usages AS pdatu
                        WHERE pdatu.payment_detail_id = payment_details.id
                            AND pdatu.tier = ?) ASC',
                    [$this->uahAmountTier->value]
                );
            }, function (Builder $query) {
                $query->orderBy('last_used_at');
            })
            ->when(! is_local(), function (Builder $query) {
                $query->lock('FOR UPDATE SKIP LOCKED');
            });
    }

    protected function logWhenBlockedByTeamLeaderReserveThreshold(): void
    {
        $sharedMode = TeamLeaderInsuranceMode::TeamLeaderReserve->value;

        $hasBlockedTrader = User::query()
            ->where('is_online', true)
            ->where('stop_traffic', false)
            ->whereNull('banned_at')
            ->whereNull('archived_at')
            ->whereNotNull('team_leader_id')
            ->whereHas('teamLeader', function (Builder $teamLeaderQuery) use ($sharedMode) {
                $teamLeaderQuery
                    ->where('team_leader_insurance_mode', $sharedMode)
                    ->whereNotNull('team_leader_reserve_stop_threshold')
                    ->whereHas('wallet', function (Builder $walletQuery) {
                        $walletQuery->whereRaw(
                            'CAST(wallets.reserve_balance AS DECIMAL(65, 0)) <= CAST(users.team_leader_reserve_stop_threshold AS DECIMAL(65, 0))'
                        );
                    });
            })
            ->exists();

        if (! $hasBlockedTrader) {
            return;
        }

        Log::info('Payment detail search found no match: traders blocked by Team Leader reserve stop threshold', [
            'reason' => TeamLeaderInsuranceService::ORDER_ISSUE_BLOCK_REASON_RESERVE_THRESHOLD,
            'merchant_id' => $this->merchant->id,
            'amount' => $this->amount->toUnits(),
            'currency' => $this->amount->getCurrency()->getCode(),
        ]);
    }
}
