<?php

namespace App\Http\Controllers\API\Payout;

use App\Exceptions\PayoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\Payout\StoreRequest;
use App\Http\Resources\API\Payout\PayoutResource;
use App\Jobs\PayoutPoolingJob;
use App\Models\Merchant;
use App\Models\MerchantApiRequestLog;
use App\Models\Payout\Payout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

class PayoutController extends Controller
{
    public function store(StoreRequest $request): JsonResponse
    {
        $merchant = queries()->merchant()->findByUUID($request->merchant_id);

        Gate::authorize('api-access-to-merchant', $merchant);

        return $this->processPayoutPooling(
            request: $request,
            merchant: $merchant,
        );
    }

    public function show(Request $request, Payout $payout): JsonResponse
    {
        Gate::authorize('api-access-to-merchant', $payout->merchant);

        $requestId = $this->logPayoutRequest($request, $payout->merchant, $this->payoutRequestData($payout, 'show'));
        $response = response()->success(
            PayoutResource::make($payout->loadMissing('merchant', 'paymentGateway'))
        );

        $this->updatePayoutLog($payout->merchant, $payout->external_id, $requestId, $response, $payout);

        return $response;
    }

    public function cancel(Request $request, Payout $payout): JsonResponse
    {
        Gate::authorize('api-access-to-merchant', $payout->merchant);

        $requestId = $this->logPayoutRequest($request, $payout->merchant, $this->payoutRequestData($payout, 'cancel'));

        try {
            $payout = services()->payout()->cancel($payout);
        } catch (PayoutException $exception) {
            $response = response()->failWithMessage($exception->getMessage());
            $this->updatePayoutLog(
                $payout->merchant,
                $payout->external_id,
                $requestId,
                $response,
                $payout,
                get_class($exception),
                $exception->getMessage(),
            );

            return $response;
        }

        $response = response()->successWithMessage(
            'Выплата отменена.',
            PayoutResource::make($payout->loadMissing('merchant', 'paymentGateway'))
        );

        $this->updatePayoutLog($payout->merchant, $payout->external_id, $requestId, $response, $payout);

        return $response;
    }

    private function processPayoutPooling(StoreRequest $request, Merchant $merchant): JsonResponse
    {
        $validated = $request->validated();
        $payload = $request->toPayoutPayload();
        $requestId = $this->logPayoutRequest($request, $merchant, [
            ...$validated,
            'payment_detail_type' => $validated['payout_method'] ?? null,
            'operation' => 'create',
        ]);
        $maxWaitMs = $this->resolveMaxWaitMs($request, $merchant);
        $pollIntervalMs = (int) config('order-pooling.poll_interval', 100);
        $createdAtMs = now()->getTimestampMs();
        $jobID = Str::uuid()->toString();

        cache()->put($this->cacheKey($jobID), json_encode([
            'status' => 'queued',
        ]), 60);

        PayoutPoolingJob::dispatch(
            $jobID,
            $createdAtMs,
            $payload,
            $maxWaitMs,
            $createdAtMs + $this->resolveCreationDeadlineMs($maxWaitMs, $pollIntervalMs),
        );

        $waitedMs = 0;

        while ($waitedMs < $maxWaitMs) {
            usleep($pollIntervalMs * 1000);
            $waitedMs += $pollIntervalMs;

            $state = $this->readState($jobID);

            if (! $state || empty($state['status'])) {
                break;
            }

            if ($state['status'] === 'done') {
                $payout = Payout::query()
                    ->withoutGlobalScopes()
                    ->find($state['payout_id']);

                if (! $payout) {
                    break;
                }

                $response = response()->success(
                    PayoutResource::make($payout->loadMissing('merchant', 'paymentGateway'))
                );

                $this->updatePayoutLog($merchant, $request->external_id, $requestId, $response, $payout);

                return $response;
            }

            if ($state['status'] === 'failed') {
                $response = $this->failedCreationResponse($state);
                $this->updatePayoutLog(
                    $merchant,
                    $request->external_id,
                    $requestId,
                    $response,
                    exceptionClass: $state['exception']['class'] ?? null,
                    exceptionMessage: $state['exception']['message'] ?? null,
                );

                return $response;
            }

            if ($state['status'] === 'expired') {
                break;
            }
        }

        cache()->put($this->cacheKey($jobID), json_encode([
            'status' => 'expired',
        ]), 60);

        $response = response()->failWithMessage(
            'Не удалось обработать запрос вовремя. Повторите попытку позже.',
            504,
        );
        $this->updatePayoutLog($merchant, $request->external_id, $requestId, $response);

        return $response;
    }

    private function logPayoutRequest(Request $request, Merchant $merchant, array $requestData): string
    {
        return services()->merchantApiLog()->logRequest(
            $request,
            $merchant,
            $requestData,
            MerchantApiRequestLog::TYPE_PAYOUT,
        );
    }

    private function updatePayoutLog(
        Merchant $merchant,
        ?string $externalId,
        string $requestId,
        JsonResponse $response,
        ?Payout $payout = null,
        ?string $exceptionClass = null,
        ?string $exceptionMessage = null,
    ): void {
        services()->merchantApiLog()->updateWithResponse(
            merchant: $merchant,
            externalID: (string) $externalId,
            requestID: $requestId,
            response: $response,
            exceptionClass: $exceptionClass,
            exceptionMessage: $exceptionMessage,
            payout: $payout,
        );
    }

    private function payoutRequestData(Payout $payout, string $operation): array
    {
        $payout->loadMissing('paymentGateway');

        return [
            'operation' => $operation,
            'payout_uuid' => $payout->uuid,
            'external_id' => $payout->external_id,
            'amount' => $payout->amount_fiat->toPrecision(),
            'currency' => strtolower($payout->amount_fiat->getCurrency()->getCode()),
            'payment_gateway' => $payout->paymentGateway?->code,
            'payment_detail_type' => $payout->payout_method_type->value,
            'status' => $payout->status->value,
        ];
    }

    private function resolveMaxWaitMs(StoreRequest $request, Merchant $merchant): int
    {
        $timeout = (int) $merchant->max_payout_wait_time;

        if ($request->headers->has('X-Max-Wait-Ms')) {
            $timeout = (int) $request->header('X-Max-Wait-Ms');
        }

        $maxWaitTime = (int) config('order-pooling.max_wait_time', 30000);
        $timeout = $timeout === 0 ? $maxWaitTime : $timeout;
        $timeout = max(1000, $timeout);

        return min($timeout, $maxWaitTime);
    }

    private function resolveCreationDeadlineMs(int $maxWaitMs, int $pollIntervalMs): int
    {
        $responseMarginMs = max($pollIntervalMs * 2, 250);

        return max(1, $maxWaitMs - $responseMarginMs);
    }

    private function failedCreationResponse(array $state): JsonResponse
    {
        $exceptionClass = $state['exception']['class'] ?? null;
        $exceptionMessage = $state['exception']['message'] ?? null;

        if ($exceptionClass && $exceptionMessage && is_a($exceptionClass, PayoutException::class, true)) {
            return response()->failWithMessage($exceptionMessage);
        }

        if ($exceptionClass && $exceptionMessage && is_a($exceptionClass, Throwable::class, true)) {
            return response()->failWithMessage('Произошла ошибка при обработке запроса');
        }

        return response()->failWithMessage('Произошла неизвестная ошибка при обработке запроса');
    }

    private function readState(string $jobID): ?array
    {
        $state = cache()->get($this->cacheKey($jobID));

        return $state ? json_decode($state, true) : null;
    }

    private function cacheKey(string $jobID): string
    {
        return "payout:create:$jobID";
    }
}
