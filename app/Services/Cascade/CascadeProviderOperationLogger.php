<?php

declare(strict_types=1);

namespace App\Services\Cascade;

use App\Enums\CascadeDealEventType;
use App\Models\CascadeDeal;
use App\Models\CascadeProvider;
use App\Models\CascadeProviderLog;
use App\Models\CascadeTransaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CascadeProviderOperationLogger
{
    public function __construct(
        private readonly CascadeDealEventRecorder $events,
    ) {}

    /**
     * @param  array<string, mixed>|null  $requestPayload
     * @param  array<string, mixed>|null  $responsePayload
     * @param  array<string, mixed>  $context
     */
    public function record(
        CascadeProvider $provider,
        string $operation,
        string $method,
        string $url,
        ?CascadeDeal $deal = null,
        ?CascadeTransaction $transaction = null,
        ?array $requestPayload = null,
        ?array $responsePayload = null,
        ?int $statusCode = null,
        ?float $startedAt = null,
        bool $isSuccessful = true,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        array $context = [],
        bool $recordDealEvent = false,
    ): CascadeProviderLog {
        $transaction ??= $this->resolveTransaction($deal, $provider, $requestPayload, $responsePayload, $context);
        $normalizedErrorCode = $errorCode !== null
            ? $this->normalizeErrorCode($errorCode, $errorMessage ?? '')
            : null;

        $log = CascadeProviderLog::query()->create([
            'cascade_deal_id' => $deal?->id,
            'cascade_transaction_id' => $transaction?->id,
            'provider_id' => $provider->id,
            'operation' => $operation,
            'method' => $method,
            'url' => $url,
            'request_payload' => $requestPayload,
            'response_payload' => CascadeProviderLog::literalHttpJsonForLog($responsePayload),
            'status_code' => $statusCode ?? $this->extractStatusCode($responsePayload),
            'execution_time' => $startedAt !== null ? round(microtime(true) - $startedAt, 4) : null,
            'is_successful' => $isSuccessful,
            'error_code' => $normalizedErrorCode,
            'error_message' => $errorMessage,
        ]);

        if ($recordDealEvent && $deal instanceof CascadeDeal) {
            $this->events->record(
                deal: $deal,
                type: $isSuccessful ? CascadeDealEventType::PROVIDER_OPERATION : CascadeDealEventType::ERROR,
                payload: [
                    'operation' => $operation,
                    'request' => $requestPayload,
                    'response' => $responsePayload,
                    'error_code' => $normalizedErrorCode,
                    'error_message' => $errorMessage,
                ],
                transaction: $transaction,
                provider: $provider,
            );
        }

        return $log;
    }

    /**
     * @param  array<string, mixed>  $requestPayload
     * @param  array<string, mixed>  $responsePayload
     * @param  array<string, mixed>  $context
     */
    public function callback(
        CascadeProvider $provider,
        string $url,
        array $requestPayload,
        array $responsePayload,
        int $statusCode,
        bool $isSuccessful,
        ?CascadeDeal $deal = null,
        ?CascadeTransaction $transaction = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        array $context = [],
    ): CascadeProviderLog {
        return $this->record(
            provider: $provider,
            operation: 'callback',
            method: 'POST',
            url: $url,
            deal: $deal,
            transaction: $transaction,
            requestPayload: $requestPayload,
            responsePayload: $responsePayload,
            statusCode: $statusCode,
            isSuccessful: $isSuccessful,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            context: $context,
        );
    }

    /**
     * @param  array<string, mixed>|null  $requestPayload
     * @param  array<string, mixed>|null  $responsePayload
     * @param  array<string, mixed>  $context
     */
    public function providerOperation(
        CascadeProvider $provider,
        string $operation,
        string $method,
        string $url,
        ?CascadeDeal $deal = null,
        ?array $requestPayload = null,
        ?array $responsePayload = null,
        ?int $statusCode = null,
        ?float $startedAt = null,
        bool $isSuccessful = true,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        array $context = [],
    ): CascadeProviderLog {
        return $this->record(
            provider: $provider,
            operation: $operation,
            method: $method,
            url: $url,
            deal: $deal,
            requestPayload: $requestPayload,
            responsePayload: $responsePayload,
            statusCode: $statusCode,
            startedAt: $startedAt,
            isSuccessful: $isSuccessful,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            context: $context,
        );
    }

    /**
     * @param  array<string, mixed>|null  $requestPayload
     * @param  array<string, mixed>|null  $responsePayload
     * @param  array<string, mixed>  $context
     */
    private function resolveTransaction(
        ?CascadeDeal $deal,
        CascadeProvider $provider,
        ?array $requestPayload,
        ?array $responsePayload,
        array $context,
    ): ?CascadeTransaction {
        if (! $deal instanceof CascadeDeal) {
            return null;
        }

        $transactionId = Arr::get($context, 'cascade_transaction_id')
            ?? Arr::get($requestPayload ?? [], 'cascade_transaction_id');

        if ($transactionId !== null) {
            return CascadeTransaction::query()
                ->whereKey((int) $transactionId)
                ->where('cascade_deal_id', $deal->id)
                ->where('provider_id', $provider->id)
                ->first();
        }

        $providerDealId = Arr::get($context, 'provider_deal_id')
            ?? Arr::get($responsePayload ?? [], 'provider_deal_id')
            ?? Arr::get($requestPayload ?? [], 'provider_deal_id');

        $query = $deal->transactions()
            ->where('provider_id', $provider->id);

        if ($providerDealId) {
            $transaction = (clone $query)
                ->where('provider_deal_id', $providerDealId)
                ->latest('id')
                ->first();

            if ($transaction instanceof CascadeTransaction) {
                return $transaction;
            }
        }

        return $query
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    private function extractStatusCode(?array $responsePayload): ?int
    {
        if ($responsePayload === null) {
            return null;
        }

        $statusCode = Arr::get($responsePayload, 'status_code')
            ?? Arr::get($responsePayload, 'http_status')
            ?? Arr::get($responsePayload, 'raw.status_code')
            ?? Arr::get($responsePayload, 'raw.status');

        if (is_int($statusCode)) {
            return $statusCode;
        }

        return is_numeric($statusCode) ? (int) $statusCode : null;
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
