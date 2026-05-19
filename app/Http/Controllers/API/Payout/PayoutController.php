<?php

namespace App\Http\Controllers\API\Payout;

use App\Exceptions\PayoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\Payout\StoreRequest;
use App\Http\Resources\API\Payout\PayoutResource;
use App\Jobs\PayoutPoolingJob;
use App\Models\Merchant;
use App\Models\Payout\Payout;
use Illuminate\Http\JsonResponse;
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

    public function show(Payout $payout): JsonResponse
    {
        Gate::authorize('api-access-to-merchant', $payout->merchant);

        return response()->success(
            PayoutResource::make($payout->loadMissing('merchant', 'paymentGateway'))
        );
    }

    public function cancel(Payout $payout): JsonResponse
    {
        Gate::authorize('api-access-to-merchant', $payout->merchant);

        try {
            $payout = services()->payout()->cancel($payout);
        } catch (PayoutException $exception) {
            return response()->failWithMessage($exception->getMessage());
        }

        return response()->successWithMessage(
            'Выплата отменена.',
            PayoutResource::make($payout->loadMissing('merchant', 'paymentGateway'))
        );
    }

    public function confirmPaid(Payout $payout): JsonResponse
    {
        Gate::authorize('api-access-to-merchant', $payout->merchant);

        try {
            $payout = services()->payout()->confirmPaid($payout);
        } catch (PayoutException $exception) {
            return response()->failWithMessage($exception->getMessage());
        }

        return response()->successWithMessage(
            'Выплата подтверждена и холд снят.',
            PayoutResource::make($payout->loadMissing('merchant', 'paymentGateway'))
        );
    }

    private function processPayoutPooling(StoreRequest $request, Merchant $merchant): JsonResponse
    {
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
            $request->validated(),
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

                return response()->success(
                    PayoutResource::make($payout->loadMissing('merchant', 'paymentGateway'))
                );
            }

            if ($state['status'] === 'failed') {
                return $this->failedCreationResponse($state);
            }

            if ($state['status'] === 'expired') {
                break;
            }
        }

        cache()->put($this->cacheKey($jobID), json_encode([
            'status' => 'expired',
        ]), 60);

        return response()->failWithMessage(
            'Не удалось обработать запрос вовремя. Повторите попытку позже.',
            504,
        );
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
