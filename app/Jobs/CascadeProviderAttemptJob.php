<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\CascadeProviderServiceContract;
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
use App\Services\Money\Money;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class CascadeProviderAttemptJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

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

        $transaction = CascadeTransaction::create([
            'cascade_deal_id' => $cascadeDeal->id,
            'provider_id' => $providerModel->id,
            'status' => CascadeTransactionStatus::OPENED,
            'request_payload' => $this->requestPayload($cascadeDeal),
        ]);
        $responsePayload = null;

        try {
            $responsePayload = $provider->createDeal($cascadeDeal);

            $transaction->update([
                'provider_deal_id' => Arr::get($responsePayload, 'provider_deal_id'),
                'response_payload' => $responsePayload,
            ]);

            $won = $this->tryAcceptWinner($cascadeDeal, $providerModel, $transaction, $responsePayload);

            if (! $won) {
                $this->cancelLoser($cascadeDeal, $providerModel, $transaction, $responsePayload);
            }
        } catch (Throwable $e) {
            $this->recordFailure(
                cascadeDeal: $cascadeDeal,
                providerModel: $providerModel,
                transaction: $transaction,
                errorCode: get_class($e),
                errorMessage: $e->getMessage(),
                responsePayload: $responsePayload,
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

            CascadeProviderLog::create([
                'cascade_deal_id' => $cascadeDeal->id,
                'cascade_transaction_id' => $transaction->id,
                'provider_id' => $providerModel->id,
                'operation' => 'cancelDeal',
                'method' => 'POST',
                'url' => $providerModel->base_url,
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
            'status' => $order?->status ?? OrderStatus::PENDING,
            'sub_status' => $order?->sub_status ?? OrderSubStatus::WAITING_FOR_DETAILS_TO_BE_SELECTED,
            'selected_provider_id' => $providerModel->id,
            'selected_transaction_id' => $transaction->id,
            'gateway' => Arr::get($responsePayload, 'gateway'),
            'details' => Arr::get($responsePayload, 'details'),
            'finished_at' => $order?->finished_at,
        ];
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
