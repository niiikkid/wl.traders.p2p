<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\CascadeProviderServiceContract;
use App\Enums\CascadeDealEventType;
use App\Enums\CascadeDealStatus;
use App\Enums\CascadeDealSubStatus;
use App\Enums\CascadeTransactionStatus;
use App\Exceptions\CascadeException;
use App\Models\CascadeDeal;
use App\Models\CascadeProvider;
use App\Models\CascadeProviderLog;
use App\Services\Cascade\CascadeDealEventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class CascadeProviderOperationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 10;

    public int $tries = 6;

    /**
     * @var list<int>
     */
    public array $backoff = [3, 5, 10, 30, 60];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $cascadeDealId,
        public int $providerId,
        public string $operation,
        public array $payload = [],
    ) {
        $this->afterCommit();
        $this->onQueue('cascade-provider-attempts');
    }

    public function handle(CascadeProviderServiceContract $providers, CascadeDealEventRecorder $events): void
    {
        $deal = CascadeDeal::query()
            ->with(['selectedTransaction', 'order'])
            ->find($this->cascadeDealId);
        $providerModel = CascadeProvider::query()->find($this->providerId);

        if (! $deal || ! $providerModel) {
            return;
        }

        $provider = $providers->getProviderByModel($providerModel);

        if (! $provider) {
            throw CascadeException::make('Интеграция провайдера каскада недоступна.');
        }

        $startedAt = microtime(true);
        $providerDealId = (string) ($this->payload['provider_deal_id'] ?? $deal->selectedTransaction?->provider_deal_id ?? $deal->order?->uuid ?? '');
        $responsePayload = null;
        $isSuccessful = false;
        $errorCode = null;
        $errorMessage = null;

        try {
            $responsePayload = match ($this->operation) {
                'cancelDeal' => $provider->cancelDeal($deal, $providerDealId),
                'storeConfirmationCode' => $provider->storeConfirmationCode($deal, (string) $this->payload['confirmation_code']),
                'openDispute' => $provider->openDispute($deal, $providerDealId, $this->payload),
                default => throw CascadeException::make('Неподдерживаемая операция провайдера.'),
            };

            if ($this->operation === 'openDispute') {
                $payload = $deal->selectedTransaction?->response_payload ?? [];
                $payload['dispute'] = $responsePayload;
                $deal->selectedTransaction?->update(['response_payload' => $payload]);
            }

            if ($this->operation === 'cancelDeal') {
                $this->syncSuccessfulExternalCancel($deal, $providerModel, $events, $responsePayload ?? []);
            }

            $isSuccessful = true;
        } catch (Throwable $e) {
            $errorCode = get_class($e);
            $errorMessage = $e->getMessage();
            throw $e;
        } finally {
            CascadeProviderLog::query()->create([
                'cascade_deal_id' => $deal->id,
                'cascade_transaction_id' => $deal->selected_transaction_id,
                'provider_id' => $providerModel->id,
                'operation' => $this->operation,
                'method' => 'POST',
                'url' => $provider->providerApiLogUrl($this->operation, $deal, $this->payload),
                'request_payload' => $this->payload,
                'response_payload' => CascadeProviderLog::literalHttpJsonForLog($responsePayload),
                'execution_time' => round(microtime(true) - $startedAt, 4),
                'is_successful' => $isSuccessful,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ]);

            $events->record(
                deal: $deal,
                type: $isSuccessful ? CascadeDealEventType::PROVIDER_OPERATION : CascadeDealEventType::ERROR,
                payload: [
                    'operation' => $this->operation,
                    'request' => $this->payload,
                    'response' => $responsePayload,
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                ],
                provider: $providerModel,
                transaction: $deal->selectedTransaction,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $responsePayload
     */
    private function syncSuccessfulExternalCancel(
        CascadeDeal $deal,
        CascadeProvider $providerModel,
        CascadeDealEventRecorder $events,
        array $responsePayload,
    ): void {
        DB::transaction(function () use ($deal, $providerModel, $events, $responsePayload): void {
            $deal->refresh();
            $deal->loadMissing(['selectedTransaction']);

            $fromStatus = $deal->status?->value;
            $fromSubStatus = $deal->sub_status?->value;

            $shouldMarkCancelled = $deal->status?->equals(CascadeDealStatus::PENDING) ?? false;

            $deal->update([
                'status' => $shouldMarkCancelled ? CascadeDealStatus::FAIL : $deal->status,
                'sub_status' => $shouldMarkCancelled ? CascadeDealSubStatus::CANCELED : $deal->sub_status,
                'finished_at' => $shouldMarkCancelled ? ($deal->finished_at ?? now()) : $deal->finished_at,
            ]);

            $deal->selectedTransaction?->update([
                'status' => CascadeTransactionStatus::CANCELLED,
                'response_payload' => $responsePayload,
            ]);

            $deal->refresh();
            $deal->loadMissing(['selectedTransaction']);

            if ($fromStatus !== $deal->status?->value || $fromSubStatus !== $deal->sub_status?->value) {
                $events->record(
                    deal: $deal,
                    type: CascadeDealEventType::STATUS_CHANGED,
                    payload: [
                        'source' => 'provider_operation',
                        'operation' => 'cancelDeal',
                        'response' => $responsePayload,
                    ],
                    provider: $providerModel,
                    transaction: $deal->selectedTransaction,
                    fromStatus: $fromStatus,
                    fromSubStatus: $fromSubStatus,
                    toStatus: $deal->status?->value,
                    toSubStatus: $deal->sub_status?->value,
                );
            }
        });

        SendCascadeDealCallbackJob::dispatch($deal->refresh());
    }
}
