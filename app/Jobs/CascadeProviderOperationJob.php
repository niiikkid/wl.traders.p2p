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
use App\Models\CascadeTransaction;
use App\Services\Cascade\CascadeDealEventRecorder;
use App\Services\Cascade\CascadeProviderCollateralService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        $transaction = $this->resolveTransaction($deal);
        $providerDealId = (string) ($this->payload['provider_deal_id'] ?? $transaction?->provider_deal_id ?? $deal->order?->uuid ?? '');
        $responsePayload = null;
        $isSuccessful = false;
        $errorCode = null;
        $errorMessage = null;

        try {
            $operationPayload = $this->operation === 'openDispute'
                ? $this->openDisputePayload()
                : $this->payload;

            $responsePayload = match ($this->operation) {
                'cancelDeal' => $provider->cancelDeal($deal, $providerDealId),
                'storeConfirmationCode' => $provider->storeConfirmationCode($deal, (string) $operationPayload['confirmation_code']),
                'openDispute' => $provider->openDispute($deal, $providerDealId, $operationPayload),
                default => throw CascadeException::make('Неподдерживаемая операция провайдера.'),
            };

            if ($this->operation === 'openDispute') {
                $payload = $deal->selectedTransaction?->response_payload ?? [];
                $payload['dispute'] = $responsePayload;
                $deal->selectedTransaction?->update(['response_payload' => $payload]);
            }

            if ($this->operation === 'cancelDeal') {
                $this->syncSuccessfulCancel($deal, $providerModel, $events, $transaction, $responsePayload ?? []);
            }

            $isSuccessful = true;
        } catch (Throwable $e) {
            $errorCode = $this->normalizeErrorCode(get_class($e), $e->getMessage());
            $errorMessage = $e->getMessage();
            throw $e;
        } finally {
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
                transaction: $transaction,
            );
        }
    }

    private function resolveTransaction(CascadeDeal $deal): ?CascadeTransaction
    {
        $transactionId = isset($this->payload['cascade_transaction_id'])
            ? (int) $this->payload['cascade_transaction_id']
            : null;

        if ($transactionId !== null) {
            return CascadeTransaction::query()
                ->whereKey($transactionId)
                ->where('cascade_deal_id', $deal->id)
                ->first();
        }

        return $deal->selectedTransaction;
    }

    /**
     * @return array<string, mixed>
     */
    private function openDisputePayload(): array
    {
        $payload = Arr::except($this->payload, ['cascade_dispute_receipts']);
        $receipts = collect((array) ($this->payload['cascade_dispute_receipts'] ?? []))
            ->map(function (mixed $receipt): ?UploadedFile {
                if (! is_array($receipt) || empty($receipt['stored_name'])) {
                    return null;
                }

                $path = storage_path('receipts/cascade/'.basename((string) $receipt['stored_name']));

                if (! is_file($path)) {
                    return null;
                }

                return new UploadedFile(
                    path: $path,
                    originalName: (string) ($receipt['original_name'] ?? basename($path)),
                    mimeType: $receipt['mime_type'] ?? null,
                    error: null,
                    test: true,
                );
            })
            ->filter()
            ->values()
            ->all();

        if ($receipts !== []) {
            $payload['receipts'] = $receipts;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $responsePayload
     */
    private function syncSuccessfulCancel(
        CascadeDeal $deal,
        CascadeProvider $providerModel,
        CascadeDealEventRecorder $events,
        ?CascadeTransaction $transaction,
        array $responsePayload,
    ): void {
        $callbackRevision = null;

        DB::transaction(function () use ($deal, $providerModel, $events, $transaction, $responsePayload, &$callbackRevision): void {
            $deal->refresh();
            $deal->loadMissing(['selectedTransaction']);

            if (! $transaction instanceof CascadeTransaction || $deal->selected_transaction_id !== $transaction->id) {
                $transaction?->update([
                    'status' => CascadeTransactionStatus::CANCELLED,
                    'response_payload' => array_merge($transaction->response_payload ?? [], [
                        'cancel' => $responsePayload,
                    ]),
                ]);

                return;
            }

            $fromStatus = $deal->status?->value;
            $fromSubStatus = $deal->sub_status?->value;

            $shouldMarkCancelled = $deal->status?->equals(CascadeDealStatus::PENDING) ?? false;

            $deal->update([
                'status' => $shouldMarkCancelled ? CascadeDealStatus::FAIL : $deal->status,
                'sub_status' => $shouldMarkCancelled ? CascadeDealSubStatus::CANCELED : $deal->sub_status,
                'finished_at' => $shouldMarkCancelled ? ($deal->finished_at ?? now()) : $deal->finished_at,
            ]);

            $transaction->update([
                'status' => CascadeTransactionStatus::CANCELLED,
                'response_payload' => $responsePayload,
            ]);

            app(CascadeProviderCollateralService::class)->releaseActiveForDeal($deal);

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

            $callbackRevision = $deal->callback_payload_revision + 1;
            $deal->forceFill(['callback_payload_revision' => $callbackRevision])->save();
        });

        if ($callbackRevision !== null) {
            SendCascadeDealCallbackJob::dispatch($deal->refresh(), $callbackRevision);
        }
    }

    private function normalizeErrorCode(string $errorCode, string $errorMessage): string
    {
        $haystack = Str::lower($errorCode.' '.$errorMessage);

        if (
            Str::contains($haystack, [
                'timeout',
                'timed out',
                'curl error 28',
                'operation timed out',
                'превышено время',
                'не удалось обработать запрос вовремя',
            ])
        ) {
            return 'timeout';
        }

        return $errorCode;
    }
}
