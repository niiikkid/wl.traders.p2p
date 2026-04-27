<?php

declare(strict_types=1);

namespace App\Services\Cascade;

use App\Contracts\CascadeProviderServiceContract;
use App\Contracts\CascadeServiceContract;
use App\DTO\Cascade\CreateCascadeDealDTO;
use App\Enums\CascadeTransactionStatus;
use App\Enums\MarketEnum;
use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Enums\ProviderType;
use App\Exceptions\CascadeException;
use App\Jobs\CascadeProviderAttemptJob;
use App\Models\CascadeDeal;
use App\Models\CascadeProvider;
use App\Models\CascadeProviderLog;
use App\Models\CascadeTransaction;
use App\Models\MerchantClient;
use App\Models\Order;
use App\Models\ValueObjects\CascadeManualControl;
use App\Services\Cascade\Providers\InternalCascadeProvider;
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
        $timeout = 30 * 1000;

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

            $providers = $this->activeCascadeProviders();

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
                CascadeProviderAttemptJob::dispatch(
                    $cascade_deal->id,
                    $provider->id,
                    $job_id,
                    now()->getTimestampMs(),
                    $max_wait_ms,
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

        if (! $provider_model->access_token) {
            $this->recordCallbackFailure($provider_model, $payload, 'provider_token_missing', 'Токен провайдера каскада не настроен.');

            throw CascadeException::make('Токен провайдера каскада не настроен.');
        }

        if (! hash_equals($provider_model->access_token, (string) $accessToken)) {
            $this->recordCallbackFailure($provider_model, $payload, 'invalid_provider_token', 'Неверный токен провайдера.');

            throw CascadeException::make('Неверный токен провайдера.');
        }

        $provider = app(CascadeProviderServiceContract::class)->getProviderByModel($provider_model);
        if (! $provider) {
            $this->recordCallbackFailure($provider_model, $payload, 'provider_unavailable', 'Интеграция провайдера каскада недоступна.');

            throw CascadeException::make('Интеграция провайдера каскада недоступна.');
        }

        $callback_data = $provider->handleCallback($payload);
        $cascade_deal = $this->resolveCallbackCascadeDeal($provider_model, $callback_data);
        $cascade_transaction = $this->resolveCallbackTransaction($cascade_deal, $provider_model, $callback_data);

        DB::transaction(function () use ($cascade_deal, $cascade_transaction, $callback_data): void {
            if (
                (! $cascade_transaction && $cascade_deal->selected_transaction_id === null)
                || $cascade_deal->selected_transaction_id === $cascade_transaction?->id
            ) {
                $cascade_deal->update($this->callbackCascadeDealAttributes($cascade_deal, $callback_data));
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

        return $response;
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
            'status' => OrderStatus::PENDING,
            'sub_status' => OrderSubStatus::WAITING_FOR_DETAILS_TO_BE_SELECTED,
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
                'description' => 'Internal liquidity provider.',
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
    private function activeCascadeProviders(): Collection
    {
        $this->ensureInternalProvider();

        return CascadeProvider::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderByDesc('weight')
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
                'description' => 'Internal liquidity provider.',
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
            'status' => $order?->status ?? OrderStatus::PENDING,
            'sub_status' => $order?->sub_status ?? OrderSubStatus::WAITING_FOR_DETAILS_TO_BE_SELECTED,
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
            'status' => $order?->status ?? $cascadeDeal->status,
            'sub_status' => $order?->sub_status ?? $cascadeDeal->sub_status,
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

        return [
            'status' => $this->callbackOrderStatus($status, $cascade_deal->status),
            'sub_status' => $this->callbackOrderSubStatus($status, $cascade_deal->sub_status),
            'finished_at' => $finished_at ?? $cascade_deal->finished_at,
        ];
    }

    private function callbackOrderStatus(string $provider_status, OrderStatus $current_status): OrderStatus
    {
        return match ($provider_status) {
            'completed', 'success', 'paid' => OrderStatus::SUCCESS,
            'cancelled', 'canceled', 'expired', 'failed', 'voided' => OrderStatus::FAIL,
            default => $current_status,
        };
    }

    private function callbackOrderSubStatus(string $provider_status, OrderSubStatus $current_sub_status): OrderSubStatus
    {
        return match ($provider_status) {
            'completed', 'success', 'paid' => OrderSubStatus::SUCCESSFULLY_PAID,
            'cancelled', 'canceled', 'voided' => OrderSubStatus::CANCELED,
            'expired' => OrderSubStatus::EXPIRED,
            'waiting_payment', 'pending' => OrderSubStatus::WAITING_FOR_PAYMENT,
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
