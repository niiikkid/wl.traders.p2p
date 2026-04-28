<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\CascadeProviderServiceContract;
use App\Enums\CascadeDealStatus;
use App\Enums\CascadeDealSubStatus;
use App\Enums\CascadeTransactionStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Enums\ProviderType;
use App\Exceptions\CascadeException;
use App\Models\CascadeDeal;
use App\Models\CascadeProvider;
use App\Models\CascadeProviderLog;
use App\Models\CascadeTransaction;
use App\Models\Order;
use App\Services\Cascade\CascadeProviderCollateralService;
use App\Services\Money\Money;
use App\Support\TraderCommissionTierResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class CascadeProviderAttemptJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 10;

    public int $tries = 1;

    public function __construct(
        public int $cascadeDealId,
        public int $providerId,
        public string $orchestrationId,
        public int $createdAt,
        public int $maxWaitMs,
    ) {
        $this->afterCommit();
        $this->onQueue('cascade-provider-attempts');
    }

    public function handle(): void
    {
        if ($this->isExpired() || $this->isCancelledBeforeStart()) {
            $this->markAttemptFinished();

            return;
        }

        $cascadeDeal = CascadeDeal::query()
            ->with(['merchant', 'merchantClient'])
            ->find($this->cascadeDealId);

        $providerModel = CascadeProvider::query()->find($this->providerId);

        if (! $cascadeDeal instanceof CascadeDeal || ! $providerModel instanceof CascadeProvider) {
            $this->markAttemptFinished();

            return;
        }

        $provider = app(CascadeProviderServiceContract::class)->getProviderByModel($providerModel);
        if (! $provider) {
            $this->recordFailure($cascadeDeal, $providerModel, null, 'provider_unavailable', 'Интеграция провайдера каскада недоступна.');
            $this->markAttemptFinished();

            return;
        }

        $externalProfits = null;
        if (! $providerModel->provider_type->equals(ProviderType::INTERNAL)) {
            try {
                $externalProfits = $this->persistExternalMerchantEconomics($cascadeDeal);
                $this->ensureExternalProviderHasSufficientBalance($providerModel, $externalProfits->convertedAmount);
            } catch (Throwable $e) {
                $this->recordFailure(
                    cascadeDeal: $cascadeDeal,
                    providerModel: $providerModel,
                    transaction: null,
                    errorCode: get_class($e),
                    errorMessage: $e->getMessage(),
                );
                $this->markAttemptFinished();

                return;
            }
        }

        $transaction = CascadeTransaction::create([
            'cascade_deal_id' => $cascadeDeal->id,
            'provider_id' => $providerModel->id,
            'status' => CascadeTransactionStatus::OPENED,
            'request_payload' => $this->requestPayload($cascadeDeal),
        ]);
        $responsePayload = null;
        $startedAt = microtime(true);
        $createDealLogUrl = $provider->providerApiLogUrl('createDeal', $cascadeDeal);

        try {
            $responsePayload = $provider->createDeal($cascadeDeal);

            $transaction->update([
                'provider_deal_id' => Arr::get($responsePayload, 'provider_deal_id'),
                'response_payload' => $responsePayload,
            ]);
            $this->recordProviderLog(
                cascadeDeal: $cascadeDeal,
                providerModel: $providerModel,
                transaction: $transaction,
                operation: 'createDeal',
                requestUrl: $createDealLogUrl,
                requestPayload: $transaction->request_payload ?? [],
                responsePayload: $responsePayload,
                startedAt: $startedAt,
                isSuccessful: true,
            );

            if (
                ! $providerModel->provider_type->equals(ProviderType::INTERNAL)
                && ! $this->externalProviderCoversMerchantCredit(
                    $cascadeDeal,
                    $providerModel,
                    $responsePayload,
                    $externalProfits,
                )
            ) {
                $this->cancelLoser($cascadeDeal, $providerModel, $transaction, $responsePayload);

                throw CascadeException::make('Внешний провайдер вернул сумму меньше обязательства перед мерчантом.');
            }

            $won = $this->tryAcceptWinner($cascadeDeal, $providerModel, $transaction, $responsePayload);

            if (! $won) {
                $this->cancelLoser($cascadeDeal, $providerModel, $transaction, $responsePayload);
            }
        } catch (Throwable $e) {
            if (
                $responsePayload !== null
                && $transaction->status->notEquals(CascadeTransactionStatus::ACCEPTED)
                && $transaction->status->notEquals(CascadeTransactionStatus::CANCELLED)
            ) {
                $this->cancelLoser($cascadeDeal, $providerModel, $transaction, $responsePayload);
            }

            $this->recordFailure(
                cascadeDeal: $cascadeDeal,
                providerModel: $providerModel,
                transaction: $transaction,
                errorCode: get_class($e),
                errorMessage: $e->getMessage(),
                responsePayload: $responsePayload,
            );
            $this->recordProviderLog(
                cascadeDeal: $cascadeDeal,
                providerModel: $providerModel,
                transaction: $transaction,
                operation: 'createDeal',
                requestUrl: $createDealLogUrl,
                requestPayload: $transaction->request_payload ?? [],
                responsePayload: $responsePayload,
                startedAt: $startedAt,
                isSuccessful: false,
                errorCode: get_class($e),
                errorMessage: $e->getMessage(),
            );
        } finally {
            $this->markAttemptFinished();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markAttemptFinished();
    }

    private function tryAcceptWinner(
        CascadeDeal $cascadeDeal,
        CascadeProvider $providerModel,
        CascadeTransaction $transaction,
        array $responsePayload,
    ): bool {
        return DB::transaction(function () use ($cascadeDeal, $providerModel, $transaction, $responsePayload): bool {
            $lockedDeal = CascadeDeal::query()
                ->whereKey($cascadeDeal->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedDeal instanceof CascadeDeal || $lockedDeal->selected_transaction_id !== null) {
                return false;
            }

            $order = $providerModel->provider_type->equals(ProviderType::INTERNAL)
                ? $this->findInternalOrder($responsePayload)
                : null;

            $transaction->update([
                'status' => CascadeTransactionStatus::ACCEPTED,
            ]);

            $lockedDeal->update($this->winnerAttributes(
                cascadeDeal: $lockedDeal,
                providerModel: $providerModel,
                transaction: $transaction,
                responsePayload: $responsePayload,
                order: $order,
            ));

            if (! $providerModel->provider_type->equals(ProviderType::INTERNAL)) {
                app(CascadeProviderCollateralService::class)->holdForWinner($lockedDeal->refresh(), $providerModel);
            }

            $this->cancelQueuedLosers($lockedDeal, $transaction);

            cache()->put($this->orchestrationKey(), json_encode([
                'status' => 'done',
                'cascade_deal_id' => $lockedDeal->id,
                'selected_transaction_id' => $transaction->id,
            ]), 60);

            return true;
        });
    }

    private function cancelLoser(
        CascadeDeal $cascadeDeal,
        CascadeProvider $providerModel,
        CascadeTransaction $transaction,
        array $responsePayload,
    ): void {
        $providerDealId = (string) Arr::get($responsePayload, 'provider_deal_id');

        if ($providerDealId === '') {
            $transaction->update(['status' => CascadeTransactionStatus::CANCELLED]);

            return;
        }

        try {
            $provider = app(CascadeProviderServiceContract::class)->getProviderByModel($providerModel);
            $cancelPayload = $provider?->cancelDeal($cascadeDeal, $providerDealId) ?? [];

            $transaction->update([
                'status' => CascadeTransactionStatus::CANCELLED,
                'response_payload' => array_merge($responsePayload, [
                    'cancel' => $cancelPayload,
                ]),
            ]);

            $cancelLogUrl = $provider?->providerApiLogUrl('cancelDeal', $cascadeDeal, ['provider_deal_id' => $providerDealId])
                ?? ($providerModel->base_url ?? $providerModel->code);

            CascadeProviderLog::create([
                'cascade_deal_id' => $cascadeDeal->id,
                'cascade_transaction_id' => $transaction->id,
                'provider_id' => $providerModel->id,
                'operation' => 'cancelDeal',
                'method' => 'POST',
                'url' => $cancelLogUrl,
                'request_payload' => ['provider_deal_id' => $providerDealId],
                'response_payload' => $cancelPayload,
                'is_successful' => true,
            ]);
        } catch (Throwable $e) {
            $transaction->update([
                'error_code' => get_class($e),
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    private function recordFailure(
        CascadeDeal $cascadeDeal,
        CascadeProvider $providerModel,
        ?CascadeTransaction $transaction,
        string $errorCode,
        string $errorMessage,
        ?array $responsePayload = null,
    ): void {
        $attributes = [
            'cascade_deal_id' => $cascadeDeal->id,
            'provider_id' => $providerModel->id,
            'status' => CascadeTransactionStatus::FAILED_TO_OPEN,
            'request_payload' => $this->requestPayload($cascadeDeal),
            'response_payload' => $responsePayload,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ];

        if ($transaction instanceof CascadeTransaction) {
            $transaction->update($attributes);

            return;
        }

        CascadeTransaction::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $requestPayload
     * @param  array<string, mixed>|null  $responsePayload
     */
    private function recordProviderLog(
        CascadeDeal $cascadeDeal,
        CascadeProvider $providerModel,
        ?CascadeTransaction $transaction,
        string $operation,
        string $requestUrl,
        array $requestPayload,
        ?array $responsePayload,
        float $startedAt,
        bool $isSuccessful,
        ?string $errorCode = null,
        ?string $errorMessage = null,
    ): void {
        CascadeProviderLog::create([
            'cascade_deal_id' => $cascadeDeal->id,
            'cascade_transaction_id' => $transaction?->id,
            'provider_id' => $providerModel->id,
            'operation' => $operation,
            'method' => 'POST',
            'url' => $requestUrl,
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload,
            'execution_time' => round(microtime(true) - $startedAt, 4),
            'is_successful' => $isSuccessful,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);
    }

    private function markAttemptFinished(): void
    {
        $finished = Cache::increment($this->finishedAttemptsKey());
        $expected = (int) cache()->get($this->expectedAttemptsKey(), 0);
        $current = cache()->get($this->orchestrationKey());
        $currentStatus = $current ? json_decode($current, true) : null;

        if ($expected > 0 && $finished >= $expected && ($currentStatus['status'] ?? null) !== 'done') {
            cache()->put($this->orchestrationKey(), json_encode([
                'status' => 'failed',
                'exception' => [
                    'class' => CascadeException::class,
                    'message' => 'Не удалось создать каскадную сделку ни у одного провайдера.',
                ],
            ]), 60);
        }
    }

    private function cancelQueuedLosers(CascadeDeal $cascadeDeal, CascadeTransaction $winnerTransaction): void
    {
        CascadeProvider::query()
            ->where('is_active', true)
            ->whereKeyNot($winnerTransaction->provider_id)
            ->pluck('id')
            ->each(fn (int $providerId) => cache()->put($this->cancelKey($cascadeDeal->id, $providerId), true, 60));
    }

    private function isExpired(): bool
    {
        if (now()->getTimestampMs() - $this->createdAt <= $this->maxWaitMs) {
            return false;
        }

        cache()->put($this->orchestrationKey(), json_encode([
            'status' => 'expired',
        ]), 60);

        return true;
    }

    private function isCancelledBeforeStart(): bool
    {
        return (bool) cache()->get($this->cancelKey($this->cascadeDealId, $this->providerId));
    }

    private function findInternalOrder(array $responsePayload): ?Order
    {
        $providerDealId = Arr::get($responsePayload, 'provider_deal_id');

        if (! $providerDealId) {
            return null;
        }

        return Order::withoutGlobalScopes()
            ->where('uuid', $providerDealId)
            ->first();
    }

    private function winnerAttributes(
        CascadeDeal $cascadeDeal,
        CascadeProvider $providerModel,
        CascadeTransaction $transaction,
        array $responsePayload,
        ?Order $order,
    ): array {
        if (! $providerModel->provider_type->equals(ProviderType::INTERNAL)) {
            $profits = $this->cascadeProfits($cascadeDeal);
            $externalProviderAmount = $this->externalProviderAmount($cascadeDeal, $responsePayload);

            return [
                'order_id' => null,
                'amount' => $cascadeDeal->amount,
                'initial_amount' => $cascadeDeal->initial_amount,
                'currency' => $cascadeDeal->currency,
                'debit' => $externalProviderAmount,
                'credit' => $profits->merchantCredit,
                'service_profit' => $externalProviderAmount->sub($profits->merchantCredit),
                'usdt_amount' => $profits->convertedAmount,
                'fee' => $profits->totalFee,
                'fee_rate' => $this->merchantCommissionRates($cascadeDeal)['total'],
                'market' => $cascadeDeal->market,
                'conversion_price' => $cascadeDeal->conversion_price,
                'rate_fixed_at' => $cascadeDeal->rate_fixed_at,
                'status' => CascadeDealStatus::PENDING,
                'sub_status' => CascadeDealSubStatus::WAITING_FOR_PAYMENT,
                'selected_provider_id' => $providerModel->id,
                'selected_transaction_id' => $transaction->id,
                'gateway' => Arr::get($responsePayload, 'gateway'),
                'details' => Arr::get($responsePayload, 'details'),
                'finished_at' => Arr::get($responsePayload, 'finished_at'),
            ];
        }

        return [
            'order_id' => $order?->id,
            'amount' => $order?->amount ?? $cascadeDeal->amount,
            'initial_amount' => $order?->base_amount ?? $cascadeDeal->initial_amount,
            'currency' => $order?->currency ?? $cascadeDeal->currency,
            'debit' => $order?->total_profit ?? Money::fromPrecision('0', 'USDT'),
            'credit' => $order?->merchant_profit ?? Money::fromPrecision('0', 'USDT'),
            'service_profit' => $order?->service_profit ?? Money::fromPrecision('0', 'USDT'),
            'usdt_amount' => $order?->merchant_profit ?? Money::fromPrecision('0', 'USDT'),
            'fee' => null,
            'fee_rate' => null,
            'market' => $order?->market ?? $cascadeDeal->market,
            'conversion_price' => $order?->conversion_price ?? $cascadeDeal->conversion_price,
            'rate_fixed_at' => $cascadeDeal->rate_fixed_at ?? $order?->created_at,
            'status' => $this->mapOrderStatus($order?->status),
            'sub_status' => $this->mapOrderSubStatus($order?->sub_status),
            'selected_provider_id' => $providerModel->id,
            'selected_transaction_id' => $transaction->id,
            'gateway' => Arr::get($responsePayload, 'gateway'),
            'details' => Arr::get($responsePayload, 'details'),
            'finished_at' => $order?->finished_at,
        ];
    }

    private function externalProviderCoversMerchantCredit(
        CascadeDeal $cascadeDeal,
        CascadeProvider $providerModel,
        array $responsePayload,
        ?object $profits = null,
    ): bool {
        $profits = $profits ?? $this->cascadeProfits($cascadeDeal);
        $externalProviderAmount = $this->externalProviderAmount($cascadeDeal, $responsePayload);

        $minProfitPercent = (string) max(0, (float) $providerModel->min_profit_percent);
        $requiredAmount = $profits->merchantCredit;

        if ((float) $minProfitPercent > 0) {
            $requiredAmount = $requiredAmount->add(
                $requiredAmount->mul(bcdiv($minProfitPercent, '100', Money::DEFAULT_PRECISION))
            );
        }

        return $externalProviderAmount->greaterOrEquals($requiredAmount);
    }

    private function persistExternalMerchantEconomics(CascadeDeal $cascadeDeal): object
    {
        $profits = $this->cascadeProfits($cascadeDeal);
        $rates = $this->merchantCommissionRates($cascadeDeal);

        CascadeDeal::query()
            ->whereKey($cascadeDeal->id)
            ->whereNull('selected_transaction_id')
            ->update([
                'usdt_amount' => $profits->convertedAmount,
                'fee' => $profits->totalFee,
                'fee_rate' => $rates['total'],
                'credit' => $profits->merchantCredit,
            ]);

        return $profits;
    }

    private function ensureExternalProviderHasSufficientBalance(CascadeProvider $providerModel, Money $requiredAmount): void
    {
        $providerModel->loadMissing('user.wallet');
        $wallet = $providerModel->user?->wallet;

        if (! $wallet) {
            throw CascadeException::make('У внешнего провайдера не привязан пользователь Provider Liquidity с кошельком.');
        }

        $currentBalance = $wallet->provider_balance ?? Money::zero('USDT');

        if ($currentBalance->lessThan($requiredAmount)) {
            throw CascadeException::make('Недостаточно средств на балансе провайдера.');
        }
    }

    private function cascadeProfits(CascadeDeal $cascadeDeal): object
    {
        if (! $cascadeDeal->conversion_price instanceof Money) {
            throw CascadeException::make('Для расчёта экономики каскада не зафиксирован курс.');
        }

        $rates = $this->merchantCommissionRates($cascadeDeal);

        return services()->profit()->calculateInBody(
            sourceAmount: $cascadeDeal->amount,
            exchangeRate: $cascadeDeal->conversion_price,
            totalFeeRate: $rates['total'],
            traderFeeRate: $rates['trader'],
        );
    }

    /**
     * @return array{total: float, trader: float}
     */
    private function merchantCommissionRates(CascadeDeal $cascadeDeal): array
    {
        $commissionSettings = $cascadeDeal->merchant->getCommissionSettingForPair(
            currency: $cascadeDeal->currency,
            detailType: $cascadeDeal->payment_method->detailType(),
        );

        if (! $commissionSettings) {
            throw CascadeException::make('Для мерчанта не настроена комиссия по валюте и типу реквизита.');
        }

        $traderRate = $commissionSettings['trader_commission_rate_for_orders'];
        $totalRate = $commissionSettings['total_service_commission_rate_for_orders'];

        if ($traderRate === null || $totalRate === null) {
            throw CascadeException::make('Для мерчанта не указана комиссия по каскадной сделке.');
        }

        if (
            (bool) ($commissionSettings['use_flexible_trader_commission_for_orders'] ?? false)
            && ! empty($commissionSettings['trader_commission_tiers_for_orders'])
            && ! empty($commissionSettings['total_service_commission_tiers_for_orders'])
        ) {
            $amount = (float) intval($cascadeDeal->amount->toBeauty());
            $traderRate = TraderCommissionTierResolver::resolveRate(
                tiers: $commissionSettings['trader_commission_tiers_for_orders'],
                amount: $amount,
                defaultRate: (float) $traderRate,
            );
            $totalRate = TraderCommissionTierResolver::resolveRate(
                tiers: $commissionSettings['total_service_commission_tiers_for_orders'],
                amount: $amount,
                defaultRate: (float) $totalRate,
            );
        }

        return [
            'total' => (float) $totalRate,
            'trader' => (float) $traderRate,
        ];
    }

    private function externalProviderAmount(CascadeDeal $cascadeDeal, array $responsePayload): Money
    {
        $amount = Arr::get($responsePayload, 'external_provider_amount')
            ?? Arr::get($responsePayload, 'provider_amount')
            ?? Arr::get($responsePayload, 'amount_usdt')
            ?? Arr::get($responsePayload, 'merchant_profit');

        if ($amount !== null) {
            return Money::fromPrecision((string) $amount, 'USDT');
        }

        if (Arr::get($responsePayload, 'currency') === 'USDT' && Arr::get($responsePayload, 'amount') !== null) {
            return Money::fromPrecision((string) Arr::get($responsePayload, 'amount'), 'USDT');
        }

        $settlementAmount = Arr::get($responsePayload, 'settlement.provider_receivable_amount')
            ?? Arr::get($responsePayload, 'raw.payment.settlement.provider_receivable_amount');
        $settlementCurrency = Arr::get($responsePayload, 'settlement.currency')
            ?? Arr::get($responsePayload, 'raw.payment.settlement.currency');

        if ($settlementAmount !== null && $settlementCurrency === 'USDT') {
            return Money::fromPrecision((string) $settlementAmount, 'USDT');
        }

        if ($settlementAmount !== null && $settlementCurrency === $cascadeDeal->currency->getCode()) {
            return Money::fromPrecision(
                amount: bcdiv((string) $settlementAmount, $cascadeDeal->conversion_price->toPrecision(), Money::DEFAULT_PRECISION),
                currency: 'USDT',
            );
        }

        throw CascadeException::make('Внешний провайдер не вернул сумму зачисления в USDT.');
    }

    private function mapOrderStatus(?OrderStatus $status): CascadeDealStatus
    {
        return match ($status?->value) {
            OrderStatus::SUCCESS->value => CascadeDealStatus::SUCCESS,
            OrderStatus::FAIL->value => CascadeDealStatus::FAIL,
            OrderStatus::PENDING->value => CascadeDealStatus::PENDING,
            default => CascadeDealStatus::PENDING,
        };
    }

    private function mapOrderSubStatus(?OrderSubStatus $subStatus): CascadeDealSubStatus
    {
        return match ($subStatus?->value) {
            OrderSubStatus::SUCCESSFULLY_PAID->value,
            OrderSubStatus::ACCEPTED->value => CascadeDealSubStatus::SUCCESSFULLY_PAID,
            OrderSubStatus::SUCCESSFULLY_PAID_BY_RESOLVED_DISPUTE->value => CascadeDealSubStatus::SUCCESSFULLY_PAID_BY_RESOLVED_DISPUTE,
            OrderSubStatus::WAITING_FOR_DISPUTE_TO_BE_RESOLVED->value => CascadeDealSubStatus::WAITING_FOR_DISPUTE_TO_BE_RESOLVED,
            OrderSubStatus::CANCELED_BY_DISPUTE->value => CascadeDealSubStatus::CANCELED_BY_DISPUTE,
            OrderSubStatus::CANCELED->value,
            OrderSubStatus::EXPIRED->value => CascadeDealSubStatus::CANCELED,
            default => CascadeDealSubStatus::WAITING_FOR_PAYMENT,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(CascadeDeal $cascadeDeal): array
    {
        return [
            'cascade_deal_id' => $cascadeDeal->uuid,
            'external_id' => $cascadeDeal->external_id,
            'amount' => $cascadeDeal->amount->toInt(),
            'currency' => $cascadeDeal->currency->getCode(),
            'payment_method' => $cascadeDeal->payment_method->value,
        ];
    }

    private function orchestrationKey(): string
    {
        return "cascade:deal:create:$this->orchestrationId";
    }

    private function expectedAttemptsKey(): string
    {
        return "cascade:deal:create:$this->orchestrationId:expected";
    }

    private function finishedAttemptsKey(): string
    {
        return "cascade:deal:create:$this->orchestrationId:finished";
    }

    private function cancelKey(int $cascadeDealId, int $providerId): string
    {
        return "cascade:deal:$cascadeDealId:provider:$providerId:cancel-create";
    }
}
