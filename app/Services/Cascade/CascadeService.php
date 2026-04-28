<?php

declare(strict_types=1);

namespace App\Services\Cascade;

use App\Contracts\CascadeProviderServiceContract;
use App\Contracts\CascadeServiceContract;
use App\DTO\Cascade\CreateCascadeDealDTO;
use App\Enums\CascadeDealEventType;
use App\Enums\CascadeDealStatus;
use App\Enums\CascadeDealSubStatus;
use App\Enums\CascadeDisputeStatus;
use App\Enums\CascadeTransactionStatus;
use App\Enums\MarketEnum;
use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Enums\ProviderType;
use App\Exceptions\CascadeException;
use App\Jobs\CascadeProviderAttemptJob;
use App\Jobs\CascadeProviderOperationJob;
use App\Jobs\SendCascadeDealCallbackJob;
use App\Models\CascadeDeal;
use App\Models\CascadeProvider;
use App\Models\CascadeProviderLog;
use App\Models\CascadeTransaction;
use App\Models\MerchantClient;
use App\Models\Order;
use App\Models\ValueObjects\CascadeManualControl;
use App\Services\Cascade\Providers\InternalCascadeProvider;
use App\Services\Cascade\Providers\SelfTestCascadeProvider;
use App\Services\Money\Money;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Центральный сервис каскада.
 *
 * Создаёт каскадную сделку и запускает гонку провайдеров через очередь.
 */
class CascadeService implements CascadeServiceContract
{
    public function createDeal(CreateCascadeDealDTO $dto): CascadeDeal
    {
        $timeout = 10 * 1000;

        $max_wait_ms = $timeout;
        $interval_ms = 100;
        $waited = 0;
        $processing_time_ms = 0;
        $max_wait_processing_ms = 3000;

        $job_id = Str::uuid()->toString();
        cache()->put("cascade:deal:create:$job_id", json_encode([
            'status' => 'processing',
        ]), 60);

        try {
            $cascade_deal = $this->createCascadeDeal($dto);

            $providers = $this->activeCascadeProviders($cascade_deal);

            if ($providers->isEmpty()) {
                throw CascadeException::make('Нет активных провайдеров каскада.');
            }

            cache()->put("cascade:deal:create:$job_id", json_encode([
                'status' => 'queued',
                'cascade_deal_id' => $cascade_deal->id,
            ]), 60);
            cache()->put("cascade:deal:create:$job_id:expected", $providers->count(), 60);
            cache()->put("cascade:deal:create:$job_id:finished", 0, 60);

            $providers->each(function (CascadeProvider $provider) use ($cascade_deal, $job_id, $max_wait_ms): void {
                $provider_wait_ms = min($max_wait_ms, max(1, (int) $provider->timeout) * 1000);

                CascadeProviderAttemptJob::dispatch(
                    $cascade_deal->id,
                    $provider->id,
                    $job_id,
                    now()->getTimestampMs(),
                    $provider_wait_ms,
                );
            });
        } catch (Throwable $e) {
            $cascade_exception = $e instanceof CascadeException
                ? $e
                : CascadeException::make($e->getMessage());

            cache()->put("cascade:deal:create:$job_id", json_encode([
                'status' => 'failed',
                'exception' => [
                    'class' => get_class($cascade_exception),
                    'message' => $cascade_exception->getMessage(),
                ],
            ]), 60);
        }

        while ($waited < $max_wait_ms) {
            usleep($interval_ms * 1000);
            $waited += $interval_ms;

            $result = cache()->get("cascade:deal:create:$job_id");

            if ($result) {
                $data = json_decode($result, true);

                if (empty($data['status'])) {
                    break;
                }

                if ($data['status'] === 'queued' && $waited > $max_wait_ms + ($interval_ms * 2)) {
                    cache()->put("cascade:deal:create:$job_id", json_encode([
                        'status' => 'expired',
                    ]), 60);
                    break;
                }

                if ($data['status'] === 'done') {
                    $cascade_deal = CascadeDeal::find($data['cascade_deal_id']);

                    if (! $cascade_deal) {
                        throw CascadeException::make('Не удалось получить каскадную сделку.');
                    }

                    return $cascade_deal;
                } elseif ($data['status'] === 'failed') {
                    if (empty($data['exception']['class']) || empty($data['exception']['message'])) {
                        throw CascadeException::make('Произошла неизвестная ошибка при обработке запроса');
                    }

                    if (is_a($data['exception']['class'], CascadeException::class, true)) {
                        throw CascadeException::make($data['exception']['message']);
                    } elseif (is_a($data['exception']['class'], Throwable::class, true)) {
                        if (is_local()) {
                            dd($data['exception']);
                        }

                        throw CascadeException::make('Произошла ошибка при обработке запроса');
                    }

                    throw CascadeException::make('Произошла неизвестная ошибка при обработке запроса');
                } elseif ($data['status'] === 'expired') {
                    break;
                } elseif ($data['status'] === 'processing') {
                    $processing_time_ms = $processing_time_ms + $interval_ms;

                    if ($processing_time_ms > $max_wait_processing_ms) {
                        break;
                    }
                }
            } else {
                break;
            }
        }

        throw CascadeException::make('Не удалось обработать запрос вовремя. Повторите попытку позже.');
    }

    public function findDealByExternalId(string $merchantUuid, string $externalId): CascadeDeal
    {
        return CascadeDeal::query()
            ->whereRelation('merchant', 'uuid', $merchantUuid)
            ->where('external_id', $externalId)
            ->firstOrFail();
    }

    public function cancelDeal(CascadeDeal $cascadeDeal): CascadeDeal
    {
        try {
            $cascadeDeal->loadMissing(['order', 'selectedProvider', 'selectedTransaction']);
            $provider_model = $cascadeDeal->selectedProvider;

            if (! $provider_model instanceof CascadeProvider) {
                throw CascadeException::make('Провайдер каскадной сделки не выбран.');
            }

            $provider = app(CascadeProviderServiceContract::class)->getProviderByModel($provider_model);
            if (! $provider) {
                throw CascadeException::make('Интеграция провайдера каскада недоступна.');
            }

            $provider_deal_id = (string) ($cascadeDeal->order?->uuid ?? $cascadeDeal->selectedTransaction?->provider_deal_id ?? '');

            if (! $provider_model->provider_type->equals(ProviderType::INTERNAL)) {
                CascadeProviderOperationJob::dispatch(
                    $cascadeDeal->id,
                    $provider_model->id,
                    'cancelDeal',
                    ['provider_deal_id' => $provider_deal_id],
                );

                return $cascadeDeal->refresh();
            }

            $started_at = microtime(true);
            $response_payload = $provider->cancelDeal($cascadeDeal, $provider_deal_id);

            $this->recordProviderLog(
                cascadeDeal: $cascadeDeal,
                providerModel: $provider_model,
                operation: 'cancelDeal',
                method: 'PATCH',
                url: $provider_model->base_url ?? $provider_model->code,
                requestPayload: ['provider_deal_id' => $provider_deal_id],
                responsePayload: $response_payload,
                startedAt: $started_at,
                isSuccessful: true,
            );

            $this->syncCascadeDealFromProviderResponse($cascadeDeal, $response_payload);

            return $cascadeDeal->refresh();
        } catch (Throwable $e) {
            throw $e instanceof CascadeException
                ? $e
                : CascadeException::make($e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function openDispute(CascadeDeal $cascadeDeal, array $data): array
    {
        $cascadeDeal->loadMissing(['order.dispute', 'selectedProvider', 'selectedTransaction']);

        [$provider_model, $provider_deal_id] = $this->selectedProviderContext($cascadeDeal);
        $provider = app(CascadeProviderServiceContract::class)->getProviderByModel($provider_model);
        if (! $provider) {
            throw CascadeException::make('Интеграция провайдера каскада недоступна.');
        }

        if (! $provider_model->provider_type->equals(ProviderType::INTERNAL)) {
            $local_payload = [
                'status' => 'opened',
                'provider_deal_id' => $provider_deal_id,
                'dispute_id' => null,
                'reason' => null,
            ];

            $this->rememberCascadeDispute($cascadeDeal, $local_payload, $data);
            CascadeProviderOperationJob::dispatch(
                $cascadeDeal->id,
                $provider_model->id,
                'openDispute',
                ['provider_deal_id' => $provider_deal_id, ...$data],
            );

            return $this->normalizeDisputeResponse($cascadeDeal->refresh(), $local_payload);
        }

        $started_at = microtime(true);

        try {
            $response_payload = $provider->openDispute($cascadeDeal, $provider_deal_id, $data);

            $this->rememberSelectedTransactionDispute($cascadeDeal, $response_payload);
            $this->rememberCascadeDispute($cascadeDeal, $response_payload, $data);
            $this->recordProviderLog(
                cascadeDeal: $cascadeDeal,
                providerModel: $provider_model,
                operation: 'openDispute',
                method: 'POST',
                url: $provider_model->base_url ?? $provider_model->code,
                requestPayload: ['provider_deal_id' => $provider_deal_id, ...$data],
                responsePayload: $response_payload,
                startedAt: $started_at,
                isSuccessful: true,
            );

            return $this->normalizeDisputeResponse($cascadeDeal->refresh(), $response_payload);
        } catch (Throwable $e) {
            $this->recordProviderLog(
                cascadeDeal: $cascadeDeal,
                providerModel: $provider_model,
                operation: 'openDispute',
                method: 'POST',
                url: $provider_model->base_url ?? $provider_model->code,
                requestPayload: ['provider_deal_id' => $provider_deal_id, ...$data],
                responsePayload: null,
                startedAt: $started_at,
                isSuccessful: false,
                errorCode: get_class($e),
                errorMessage: $e->getMessage(),
            );

            throw $e instanceof CascadeException
                ? $e
                : CascadeException::make($e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getDispute(CascadeDeal $cascadeDeal): array
    {
        $cascadeDeal->loadMissing(['order.dispute', 'selectedProvider', 'selectedTransaction']);

        [$provider_model, $provider_deal_id] = $this->selectedProviderContext($cascadeDeal);
        $provider = app(CascadeProviderServiceContract::class)->getProviderByModel($provider_model);
        if (! $provider) {
            throw CascadeException::make('Интеграция провайдера каскада недоступна.');
        }

        if (! $provider_model->provider_type->equals(ProviderType::INTERNAL)) {
            return $this->normalizeDisputeResponse($cascadeDeal, [
                'status' => $cascadeDeal->dispute_status?->value ?? 'opened',
                'provider_deal_id' => $provider_deal_id,
            ]);
        }

        $dispute_id = (string) (
            Arr::get($cascadeDeal->selectedTransaction?->response_payload ?? [], 'dispute.dispute_id')
            ?? $cascadeDeal->order?->dispute?->id
            ?? ''
        );

        $started_at = microtime(true);

        try {
            $response_payload = $provider->getDispute($cascadeDeal, $provider_deal_id, $dispute_id);

            $this->rememberSelectedTransactionDispute($cascadeDeal, $response_payload);
            $this->rememberCascadeDispute($cascadeDeal, $response_payload, []);
            $this->recordProviderLog(
                cascadeDeal: $cascadeDeal,
                providerModel: $provider_model,
                operation: 'getDispute',
                method: 'GET',
                url: $provider_model->base_url ?? $provider_model->code,
                requestPayload: [
                    'provider_deal_id' => $provider_deal_id,
                    'dispute_id' => $dispute_id,
                ],
                responsePayload: $response_payload,
                startedAt: $started_at,
                isSuccessful: true,
            );

            return $this->normalizeDisputeResponse($cascadeDeal->refresh(), $response_payload);
        } catch (Throwable $e) {
            $this->recordProviderLog(
                cascadeDeal: $cascadeDeal,
                providerModel: $provider_model,
                operation: 'getDispute',
                method: 'GET',
                url: $provider_model->base_url ?? $provider_model->code,
                requestPayload: [
                    'provider_deal_id' => $provider_deal_id,
                    'dispute_id' => $dispute_id,
                ],
                responsePayload: null,
                startedAt: $started_at,
                isSuccessful: false,
                errorCode: get_class($e),
                errorMessage: $e->getMessage(),
            );

            throw $e instanceof CascadeException
                ? $e
                : CascadeException::make($e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelDispute(CascadeDeal $cascadeDeal, ?string $reason = null): array
    {
        $cascadeDeal->loadMissing(['order.dispute', 'selectedProvider', 'selectedTransaction']);

        $provider_model = $cascadeDeal->selectedProvider;

        if (! $provider_model instanceof CascadeProvider || $provider_model->code !== InternalCascadeProvider::CODE) {
            throw CascadeException::make('Выбранный провайдер не поддерживает отмену спора через API.');
        }

        $dispute = $cascadeDeal->order?->dispute;
        if (! $dispute) {
            throw CascadeException::make('По сделке пока что небыло споров.');
        }

        $started_at = microtime(true);

        try {
            services()->dispute()->cancel($dispute->id, $reason ?? '');
        } catch (Throwable $e) {
            $this->recordProviderLog(
                cascadeDeal: $cascadeDeal,
                providerModel: $provider_model,
                operation: 'cancelDispute',
                method: 'PATCH',
                url: $provider_model->code,
                requestPayload: [
                    'dispute_id' => $dispute->id,
                    'reason' => $reason,
                ],
                responsePayload: null,
                startedAt: $started_at,
                isSuccessful: false,
                errorCode: get_class($e),
                errorMessage: $e->getMessage(),
            );

            throw $e instanceof CascadeException
                ? $e
                : CascadeException::make($e->getMessage());
        }

        $response_payload = [
            'dispute_id' => (string) $dispute->id,
            'provider_deal_id' => $cascadeDeal->order?->uuid,
            'status' => 'canceled',
            'cancel_reason' => $reason,
        ];

        $this->recordProviderLog(
            cascadeDeal: $cascadeDeal,
            providerModel: $provider_model,
            operation: 'cancelDispute',
            method: 'PATCH',
            url: $provider_model->code,
            requestPayload: [
                'dispute_id' => $dispute->id,
                'reason' => $reason,
            ],
            responsePayload: $response_payload,
            startedAt: $started_at,
            isSuccessful: true,
        );

        return $this->normalizeDisputeResponse($cascadeDeal->refresh(), $response_payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function storeConfirmationCode(CascadeDeal $cascadeDeal, string $confirmationCode): array
    {
        try {
            $cascadeDeal->loadMissing(['order', 'selectedProvider', 'selectedTransaction']);
            $provider_model = $cascadeDeal->selectedProvider;

            if (! $provider_model instanceof CascadeProvider) {
                throw CascadeException::make('Провайдер каскадной сделки не выбран.');
            }

            $provider = app(CascadeProviderServiceContract::class)->getProviderByModel($provider_model);
            if (! $provider) {
                throw CascadeException::make('Интеграция провайдера каскада недоступна.');
            }

            if (! $provider_model->provider_type->equals(ProviderType::INTERNAL)) {
                CascadeProviderOperationJob::dispatch(
                    $cascadeDeal->id,
                    $provider_model->id,
                    'storeConfirmationCode',
                    ['confirmation_code' => $confirmationCode],
                );

                return [
                    'payin_id' => $cascadeDeal->uuid,
                    'status' => 'queued',
                ];
            }

            $started_at = microtime(true);
            $response_payload = $provider->storeConfirmationCode(
                $cascadeDeal,
                $confirmationCode,
            );

            $this->recordProviderLog(
                cascadeDeal: $cascadeDeal,
                providerModel: $provider_model,
                operation: 'storeConfirmationCode',
                method: 'POST',
                url: $provider_model->base_url ?? $provider_model->code,
                requestPayload: ['confirmation_code' => $confirmationCode],
                responsePayload: $response_payload,
                startedAt: $started_at,
                isSuccessful: true,
            );

            return $response_payload;
        } catch (Throwable $e) {
            throw $e instanceof CascadeException
                ? $e
                : CascadeException::make($e->getMessage());
        }
    }

    public function handleProviderCallback(string $providerCode, array $payload, ?string $accessToken = null): array
    {
        $provider_model = CascadeProvider::query()
            ->where('code', $providerCode)
            ->first();

        if (! $provider_model instanceof CascadeProvider) {
            throw CascadeException::make('Провайдер каскада не найден.');
        }

        $provider = app(CascadeProviderServiceContract::class)->getProviderByModel($provider_model);
        if (! $provider) {
            $this->recordCallbackFailure($provider_model, $payload, 'provider_unavailable', 'Интеграция провайдера каскада недоступна.');

            throw CascadeException::make('Интеграция провайдера каскада недоступна.');
        }

        $callback_data = $provider->handleCallback($payload);
        $cascade_deal = $this->resolveCallbackCascadeDeal($provider_model, $callback_data);

        if ($provider_model->code === SelfTestCascadeProvider::CODE) {
            $expectedToken = (string) $cascade_deal->merchant->user->api_access_token;
        } else {
            $expectedToken = (string) $provider_model->access_token;
        }

        if ($expectedToken === '') {
            $this->recordCallbackFailure($provider_model, $payload, 'provider_token_missing', 'Токен провайдера каскада не настроен.');

            throw CascadeException::make('Токен провайдера каскада не настроен.');
        }

        if (! hash_equals($expectedToken, (string) $accessToken)) {
            $this->recordCallbackFailure($provider_model, $payload, 'invalid_provider_token', 'Неверный токен провайдера.');

            throw CascadeException::make('Неверный токен провайдера.');
        }

        $cascade_transaction = $this->resolveCallbackTransaction($cascade_deal, $provider_model, $callback_data);

        DB::transaction(function () use ($cascade_deal, $cascade_transaction, $callback_data, $provider_model): void {
            $from_status = $cascade_deal->status?->value;
            $from_sub_status = $cascade_deal->sub_status?->value;

            if (
                (! $cascade_transaction && $cascade_deal->selected_transaction_id === null)
                || $cascade_deal->selected_transaction_id === $cascade_transaction?->id
            ) {
                $cascade_deal->update($this->callbackCascadeDealAttributes($cascade_deal, $callback_data));

                app(CascadeDealEventRecorder::class)->record(
                    deal: $cascade_deal->refresh(),
                    type: CascadeDealEventType::PROVIDER_CALLBACK_RECEIVED,
                    payload: $callback_data,
                    transaction: $cascade_transaction,
                    provider: $provider_model,
                    fromStatus: $from_status,
                    fromSubStatus: $from_sub_status,
                    toStatus: $cascade_deal->status?->value,
                    toSubStatus: $cascade_deal->sub_status?->value,
                );
            }

            if ($cascade_transaction) {
                $cascade_transaction->update([
                    'response_payload' => $callback_data,
                    'status' => $this->callbackTransactionStatus(
                        (string) Arr::get($callback_data, 'status'),
                        $cascade_transaction->status,
                    ),
                ]);
            }
        });

        $response = [
            'provider_code' => $providerCode,
            'cascade_deal_id' => $cascade_deal->uuid,
            'provider_deal_id' => Arr::get($callback_data, 'provider_deal_id'),
            'status' => $cascade_deal->refresh()->status->value,
        ];

        CascadeProviderLog::create([
            'cascade_deal_id' => $cascade_deal->id,
            'cascade_transaction_id' => $cascade_transaction?->id,
            'provider_id' => $provider_model->id,
            'operation' => 'callback',
            'method' => 'POST',
            'url' => request()->fullUrl(),
            'request_payload' => $payload,
            'response_payload' => $response,
            'status_code' => 200,
            'is_successful' => true,
        ]);

        SendCascadeDealCallbackJob::dispatch($cascade_deal->refresh());

        return $response;
    }

    /**
     * @return array{0: CascadeProvider, 1: string}
     */
    private function selectedProviderContext(CascadeDeal $cascadeDeal): array
    {
        $provider_model = $cascadeDeal->selectedProvider;

        if (! $provider_model instanceof CascadeProvider) {
            throw CascadeException::make('Провайдер каскадной сделки не выбран.');
        }

        $provider_deal_id = (string) ($cascadeDeal->order?->uuid ?? $cascadeDeal->selectedTransaction?->provider_deal_id ?? '');
        if ($provider_deal_id === '') {
            throw CascadeException::make('ID сделки у провайдера не найден.');
        }

        return [$provider_model, $provider_deal_id];
    }

    /**
     * @param  array<string, mixed>  $responsePayload
     */
    private function rememberSelectedTransactionDispute(CascadeDeal $cascadeDeal, array $responsePayload): void
    {
        $transaction = $cascadeDeal->selectedTransaction;

        if (! $transaction) {
            return;
        }

        $payload = $transaction->response_payload ?? [];
        $payload['dispute'] = [
            'dispute_id' => Arr::get($responsePayload, 'dispute_id'),
            'provider_deal_id' => Arr::get($responsePayload, 'provider_deal_id'),
            'status' => Arr::get($responsePayload, 'status'),
            'cancel_reason' => Arr::get($responsePayload, 'cancel_reason'),
            'raw' => Arr::get($responsePayload, 'raw'),
        ];

        $transaction->update([
            'response_payload' => $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $responsePayload
     * @return array<string, mixed>
     */
    private function normalizeDisputeResponse(CascadeDeal $cascadeDeal, array $responsePayload): array
    {
        return [
            'payin_id' => $cascadeDeal->uuid,
            'status' => $cascadeDeal->dispute_status?->value ?? $this->mapProviderDisputeStatus((string) Arr::get($responsePayload, 'status'))?->value,
            'reason' => $cascadeDeal->dispute_reason,
            'canceled_at' => $cascadeDeal->dispute_canceled_at?->getTimestamp(),
        ];
    }

    /**
     * @param  array<string, mixed>  $responsePayload
     * @param  array<string, mixed>  $requestData
     */
    private function rememberCascadeDispute(CascadeDeal $cascadeDeal, array $responsePayload, array $requestData): void
    {
        $status = $this->mapProviderDisputeStatus((string) Arr::get($responsePayload, 'status', 'opened'));
        $reason = Arr::get($responsePayload, 'reason')
            ?? Arr::get($responsePayload, 'cancel_reason')
            ?? $cascadeDeal->dispute_reason;
        $history = $cascadeDeal->dispute_history ?? [];

        $history[] = [
            'status' => $status?->value,
            'reason' => $reason,
            'provider_deal_id' => Arr::get($responsePayload, 'provider_deal_id'),
            'dispute_id' => Arr::get($responsePayload, 'dispute_id'),
            'changed_at' => now()->toDateTimeString(),
        ];

        $receipts = $cascadeDeal->dispute_receipts ?? [];
        if (! empty($requestData['receipts'])) {
            $receipts[] = [
                'count' => count($requestData['receipts']),
                'stored_at' => now()->toDateTimeString(),
            ];
        }

        $cascadeDeal->update([
            'dispute_status' => $status,
            'dispute_reason' => $reason,
            'dispute_receipts' => $receipts,
            'dispute_history' => $history,
            'dispute_canceled_at' => $status?->equals(CascadeDisputeStatus::REJECTED) ? now() : $cascadeDeal->dispute_canceled_at,
        ]);

        app(CascadeDealEventRecorder::class)->record(
            deal: $cascadeDeal->refresh(),
            type: CascadeDealEventType::DISPUTE_CHANGED,
            payload: end($history) ?: [],
        );
    }

    private function mapProviderDisputeStatus(string $status): ?CascadeDisputeStatus
    {
        return match ($status) {
            'opened', 'pending' => CascadeDisputeStatus::OPENED,
            'accepted' => CascadeDisputeStatus::ACCEPTED,
            'rejected', 'canceled', 'cancelled' => CascadeDisputeStatus::REJECTED,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $requestPayload
     * @param  array<string, mixed>|null  $responsePayload
     */
    private function recordProviderLog(
        CascadeDeal $cascadeDeal,
        CascadeProvider $providerModel,
        string $operation,
        string $method,
        string $url,
        array $requestPayload,
        ?array $responsePayload,
        float $startedAt,
        bool $isSuccessful,
        ?string $errorCode = null,
        ?string $errorMessage = null,
    ): void {
        CascadeProviderLog::create([
            'cascade_deal_id' => $cascadeDeal->id,
            'cascade_transaction_id' => $cascadeDeal->selected_transaction_id,
            'provider_id' => $providerModel->id,
            'operation' => $operation,
            'method' => $method,
            'url' => $url,
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload,
            'execution_time' => round(microtime(true) - $startedAt, 4),
            'is_successful' => $isSuccessful,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordCallbackFailure(
        CascadeProvider $providerModel,
        array $payload,
        string $errorCode,
        string $errorMessage,
    ): void {
        CascadeProviderLog::create([
            'provider_id' => $providerModel->id,
            'operation' => 'callback',
            'method' => 'POST',
            'url' => request()->fullUrl(),
            'request_payload' => $payload,
            'response_payload' => [
                'message' => $errorMessage,
            ],
            'status_code' => 422,
            'is_successful' => false,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);
    }

    private function createCascadeDeal(CreateCascadeDealDTO $dto): CascadeDeal
    {
        $merchant_client_id = null;

        if ($dto->clientId) {
            $merchant_client_id = MerchantClient::query()->firstOrCreate([
                'merchant_id' => $dto->merchantId,
                'client_id' => $dto->clientId,
            ])->id;
        }

        $attributes = [
            'uuid' => (string) Str::uuid(),
            'external_id' => $dto->externalId,
            'merchant_id' => $dto->merchantId,
            'merchant_client_id' => $merchant_client_id,
            'amount' => $dto->amount,
            'initial_amount' => $dto->amount,
            'currency' => $dto->currency,
            'status' => CascadeDealStatus::PENDING,
            'sub_status' => CascadeDealSubStatus::WAITING_FOR_PAYMENT,
            'payment_method' => $dto->paymentMethod,
            'manual_control' => CascadeManualControl::make(
                manualControlAcquiring: $dto->manualControlAcquiring,
                cardNumber: $dto->cardNumber,
                expiryMonth: $dto->expiryMonth,
                expiryYear: $dto->expiryYear,
                cvc: $dto->cvc,
                cardholderName: $dto->cardholderName,
            ),
            'callback_url' => $dto->callbackUrl,
        ];

        if ($dto->rate !== null) {
            $attributes['market'] = MarketEnum::MERCHANT_API;
            $attributes['conversion_price'] = Money::fromPrecision($dto->rate, $dto->currency);
            $attributes['rate_fixed_at'] = now();
        }

        return CascadeDeal::create($attributes);
    }

    private function createInternalProviderDeal(CascadeDeal $cascade_deal, array $payload): void
    {
        $provider_model = CascadeProvider::query()->firstOrCreate(
            ['code' => InternalCascadeProvider::CODE],
            [
                'name' => 'Internal',
                'provider_type' => ProviderType::INTERNAL,
                'is_active' => true,
                'priority' => 0,
                'timeout' => 10,
                'min_profit_percent' => 0,
            ],
        );

        $provider = new InternalCascadeProvider(InternalCascadeProvider::CODE);

        try {
            $response_payload = $provider->createDeal($cascade_deal->loadMissing(['merchant', 'merchantClient']));
        } catch (Throwable $e) {
            CascadeTransaction::create([
                'cascade_deal_id' => $cascade_deal->id,
                'provider_id' => $provider_model->id,
                'status' => CascadeTransactionStatus::FAILED_TO_OPEN,
                'request_payload' => $payload,
                'error_code' => get_class($e),
                'error_message' => $e->getMessage(),
            ]);

            throw $e instanceof CascadeException
                ? $e
                : CascadeException::make($e->getMessage());
        }

        DB::transaction(function () use ($cascade_deal, $provider_model, $payload, $response_payload): void {
            $order = $this->findInternalOrder($response_payload);

            $transaction = CascadeTransaction::create([
                'cascade_deal_id' => $cascade_deal->id,
                'provider_id' => $provider_model->id,
                'status' => CascadeTransactionStatus::ACCEPTED,
                'provider_deal_id' => Arr::get($response_payload, 'provider_deal_id'),
                'request_payload' => $payload,
                'response_payload' => $response_payload,
            ]);

            $cascade_deal->update($this->cascadeDealWinnerAttributes(
                cascade_deal: $cascade_deal,
                provider_model: $provider_model,
                transaction: $transaction,
                response_payload: $response_payload,
                order: $order,
            ));
        });
    }

    /**
     * @return Collection<int, CascadeProvider>
     */
    private function activeCascadeProviders(CascadeDeal $cascadeDeal): Collection
    {
        $this->ensureInternalProvider();
        $setting = $cascadeDeal->merchant->cascadeSetting;

        if ($setting && ! $setting->cascade_enabled) {
            return collect();
        }

        $allowedProviderIds = collect($setting?->allowed_provider_ids ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->values();

        return CascadeProvider::query()
            ->where('is_active', true)
            ->when($setting && ! $setting->allow_internal_providers, fn ($query) => $query->where('provider_type', '!=', ProviderType::INTERNAL->value))
            ->when($setting && ! $setting->allow_external_providers, fn ($query) => $query->where('provider_type', '!=', ProviderType::EXTERNAL->value))
            ->when($allowedProviderIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $allowedProviderIds))
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->filter(fn (CascadeProvider $provider) => app(CascadeProviderServiceContract::class)->getProviderByModel($provider) !== null)
            ->values();
    }

    private function ensureInternalProvider(): CascadeProvider
    {
        return CascadeProvider::query()->firstOrCreate(
            ['code' => InternalCascadeProvider::CODE],
            [
                'name' => 'Internal',
                'provider_type' => ProviderType::INTERNAL,
                'is_active' => true,
                'priority' => 0,
                'timeout' => 10,
                'min_profit_percent' => 0,
            ],
        );
    }

    private function findInternalOrder(array $response_payload): ?Order
    {
        $provider_deal_id = Arr::get($response_payload, 'provider_deal_id');

        if (! $provider_deal_id) {
            return null;
        }

        return Order::withoutGlobalScopes()
            ->where('uuid', $provider_deal_id)
            ->first();
    }

    private function cascadeDealWinnerAttributes(
        CascadeDeal $cascade_deal,
        CascadeProvider $provider_model,
        CascadeTransaction $transaction,
        array $response_payload,
        ?Order $order,
    ): array {
        return [
            'order_id' => $order?->id,
            'amount' => $order?->amount ?? $cascade_deal->amount,
            'initial_amount' => $order?->base_amount ?? $cascade_deal->initial_amount,
            'currency' => $order?->currency ?? $cascade_deal->currency,
            'debit' => $order?->total_profit ?? Money::fromPrecision('0', 'USDT'),
            'credit' => $order?->merchant_profit ?? Money::fromPrecision('0', 'USDT'),
            'service_profit' => $order?->service_profit ?? Money::fromPrecision('0', 'USDT'),
            'usdt_amount' => $order?->merchant_profit ?? Money::fromPrecision('0', 'USDT'),
            'fee' => null,
            'fee_rate' => null,
            'market' => $order?->market ?? $cascade_deal->market,
            'conversion_price' => $order?->conversion_price ?? $cascade_deal->conversion_price,
            'rate_fixed_at' => $cascade_deal->rate_fixed_at ?? $order?->created_at,
            'status' => $this->mapOrderStatus($order?->status),
            'sub_status' => $this->mapOrderSubStatus($order?->sub_status),
            'selected_provider_id' => $provider_model->id,
            'selected_transaction_id' => $transaction->id,
            'gateway' => Arr::get($response_payload, 'gateway'),
            'details' => Arr::get($response_payload, 'details'),
            'finished_at' => $order?->finished_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $response_payload
     */
    private function syncCascadeDealFromProviderResponse(CascadeDeal $cascadeDeal, array $response_payload): void
    {
        $order = $cascadeDeal->order()->withoutGlobalScopes()->first();

        $cascadeDeal->update([
            'status' => $this->mapOrderStatus($order?->status, $cascadeDeal->status),
            'sub_status' => $this->mapOrderSubStatus($order?->sub_status, $cascadeDeal->sub_status),
            'gateway' => Arr::get($response_payload, 'gateway', $cascadeDeal->gateway),
            'details' => Arr::get($response_payload, 'details', $cascadeDeal->details),
            'finished_at' => $order?->finished_at ?? $cascadeDeal->finished_at,
        ]);

        if ($cascadeDeal->selectedTransaction) {
            $cascadeDeal->selectedTransaction->update([
                'response_payload' => $response_payload,
                'status' => $order?->status?->equals(OrderStatus::FAIL) === true
                    ? CascadeTransactionStatus::CANCELLED
                    : $cascadeDeal->selectedTransaction->status,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $callback_data
     */
    private function resolveCallbackCascadeDeal(CascadeProvider $provider_model, array $callback_data): CascadeDeal
    {
        $cascade_deal_uuid = Arr::get($callback_data, 'cascade_deal_uuid');
        if ($cascade_deal_uuid) {
            $cascade_deal = CascadeDeal::query()
                ->where('uuid', $cascade_deal_uuid)
                ->first();

            if ($cascade_deal instanceof CascadeDeal) {
                return $cascade_deal;
            }
        }

        $provider_deal_id = Arr::get($callback_data, 'provider_deal_id');
        if ($provider_deal_id) {
            $cascade_transaction = CascadeTransaction::query()
                ->where('provider_id', $provider_model->id)
                ->where('provider_deal_id', $provider_deal_id)
                ->first();

            if ($cascade_transaction instanceof CascadeTransaction) {
                return $cascade_transaction->cascadeDeal;
            }
        }

        throw CascadeException::make('Каскадная сделка для callback не найдена.');
    }

    /**
     * @param  array<string, mixed>  $callback_data
     */
    private function resolveCallbackTransaction(
        CascadeDeal $cascade_deal,
        CascadeProvider $provider_model,
        array $callback_data,
    ): ?CascadeTransaction {
        $provider_deal_id = Arr::get($callback_data, 'provider_deal_id');

        return $cascade_deal->transactions()
            ->where('provider_id', $provider_model->id)
            ->when($provider_deal_id, fn ($query) => $query->where('provider_deal_id', $provider_deal_id))
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $callback_data
     * @return array<string, mixed>
     */
    private function callbackCascadeDealAttributes(CascadeDeal $cascade_deal, array $callback_data): array
    {
        $status = (string) Arr::get($callback_data, 'status');
        $finished_at = $this->callbackFinishedAt($callback_data);
        $attributes = [
            'status' => $this->callbackOrderStatus($status, $cascade_deal->status),
            'sub_status' => $this->callbackOrderSubStatus($status, $cascade_deal->sub_status),
            'finished_at' => $finished_at ?? $cascade_deal->finished_at,
        ];

        $callbackAmount = Arr::get($callback_data, 'amount');
        $callbackCurrency = Arr::get($callback_data, 'currency');

        if ($callbackAmount !== null && $callbackCurrency === $cascade_deal->currency?->getCode()) {
            $attributes['amount'] = Money::fromPrecision((string) $callbackAmount, $callbackCurrency);

            app(CascadeDealEventRecorder::class)->record(
                deal: $cascade_deal,
                type: CascadeDealEventType::AMOUNT_CHANGED,
                payload: [
                    'source' => 'provider_callback',
                    'old_amount' => $cascade_deal->amount?->toBeauty(),
                    'new_amount' => (string) $callbackAmount,
                    'currency' => $callbackCurrency,
                ],
            );
        }

        $confirmationType = Arr::get($callback_data, 'confirmation_type');
        $rejectReason = Arr::get($callback_data, 'reject_reason');

        if ($confirmationType !== null || $rejectReason !== null) {
            $manualControl = $cascade_deal->manual_control;
            $attributes['manual_control'] = CascadeManualControl::make(
                manualControlAcquiring: true,
                cardNumber: $manualControl?->cardNumber,
                expiryMonth: $manualControl?->expiryMonth,
                expiryYear: $manualControl?->expiryYear,
                cvc: $manualControl?->cvc,
                cardholderName: $manualControl?->cardholderName,
                confirmationType: $confirmationType ? (string) $confirmationType : $manualControl?->confirmationType,
                rejectReason: $rejectReason ? (string) $rejectReason : $manualControl?->rejectReason,
            );
        }

        return $attributes;
    }

    private function callbackOrderStatus(string $provider_status, CascadeDealStatus $current_status): CascadeDealStatus
    {
        return match ($provider_status) {
            'completed', 'success', 'paid' => CascadeDealStatus::SUCCESS,
            'cancelled', 'canceled', 'expired', 'failed', 'voided' => CascadeDealStatus::FAIL,
            default => $current_status,
        };
    }

    private function callbackOrderSubStatus(string $provider_status, CascadeDealSubStatus $current_sub_status): CascadeDealSubStatus
    {
        return match ($provider_status) {
            'completed', 'success', 'paid' => CascadeDealSubStatus::SUCCESSFULLY_PAID,
            'cancelled', 'canceled', 'expired', 'failed', 'voided' => CascadeDealSubStatus::CANCELED,
            'waiting_payment', 'pending' => CascadeDealSubStatus::WAITING_FOR_PAYMENT,
            default => $current_sub_status,
        };
    }

    private function callbackTransactionStatus(
        string $provider_status,
        CascadeTransactionStatus $current_status,
    ): CascadeTransactionStatus {
        return match ($provider_status) {
            'cancelled', 'canceled', 'expired', 'failed', 'voided' => CascadeTransactionStatus::CANCELLED,
            default => $current_status,
        };
    }

    private function mapOrderStatus(?OrderStatus $status, ?CascadeDealStatus $default = null): CascadeDealStatus
    {
        return match ($status?->value) {
            OrderStatus::SUCCESS->value => CascadeDealStatus::SUCCESS,
            OrderStatus::FAIL->value => CascadeDealStatus::FAIL,
            OrderStatus::PENDING->value => CascadeDealStatus::PENDING,
            default => $default ?? CascadeDealStatus::PENDING,
        };
    }

    private function mapOrderSubStatus(?OrderSubStatus $subStatus, ?CascadeDealSubStatus $default = null): CascadeDealSubStatus
    {
        return match ($subStatus?->value) {
            OrderSubStatus::SUCCESSFULLY_PAID->value,
            OrderSubStatus::ACCEPTED->value => CascadeDealSubStatus::SUCCESSFULLY_PAID,
            OrderSubStatus::SUCCESSFULLY_PAID_BY_RESOLVED_DISPUTE->value => CascadeDealSubStatus::SUCCESSFULLY_PAID_BY_RESOLVED_DISPUTE,
            OrderSubStatus::WAITING_FOR_DISPUTE_TO_BE_RESOLVED->value => CascadeDealSubStatus::WAITING_FOR_DISPUTE_TO_BE_RESOLVED,
            OrderSubStatus::CANCELED_BY_DISPUTE->value => CascadeDealSubStatus::CANCELED_BY_DISPUTE,
            OrderSubStatus::CANCELED->value,
            OrderSubStatus::EXPIRED->value => CascadeDealSubStatus::CANCELED,
            default => $default ?? CascadeDealSubStatus::WAITING_FOR_PAYMENT,
        };
    }

    /**
     * @param  array<string, mixed>  $callback_data
     */
    private function callbackFinishedAt(array $callback_data): ?Carbon
    {
        $timestamp = Arr::get($callback_data, 'data.occurred_at')
            ?? Arr::get($callback_data, 'data.sent_at');

        return is_numeric($timestamp) ? Carbon::createFromTimestamp((int) $timestamp) : null;
    }
}
