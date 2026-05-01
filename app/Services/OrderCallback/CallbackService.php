<?php

namespace App\Services\OrderCallback;

use App\Contracts\CallbackServiceContract;
use App\Http\Resources\API\H2H\OrderResource;
use App\Http\Resources\API\Payout\PayoutCallbackResource;
use App\Http\Resources\API\V2\PayoutResource as PayoutV2Resource;
use App\Jobs\RecordCascadeMerchantLogJob;
use App\Models\CallbackLog;
use App\Models\CascadeMerchantLog;
use App\Models\Order;
use App\Models\Payout\Payout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Throwable;

class CallbackService implements CallbackServiceContract
{
    public function sendForOrder(Order $order): void
    {
        $order->load(['paymentDetail', 'paymentGateway', 'smsLog', 'merchant.user', 'dispute']);

        $callback_url = $order->callback_url ?? $order->merchant->callback_url;

        if (! $callback_url) {
            return;
        }

        if ($order->is_h2h) {
            $data = OrderResource::make($order)->resolve();
        } else {
            $data = \App\Http\Resources\API\Merchant\OrderResource::make($order)->resolve();
        }

        $token = $order->merchant->user->api_access_token;

        $this->sendCallback($callback_url, $data, $token, $order, CallbackLog::TYPE_ORDER);
    }

    public function sendForPayout(Payout $payout): void
    {
        $payout->load(['merchant.user', 'paymentGateway', 'trader']);

        $callbackUrl = $payout->callback_url
            ?? $payout->merchant->payout_callback_url;

        if (! $callbackUrl) {
            return;
        }

        $data = $payout->api_version === 2
            ? PayoutV2Resource::make($payout)->resolve()
            : PayoutCallbackResource::make($payout)->resolve();
        $token = $payout->api_version === 2
            ? $payout->merchant->apiCredentialOrCreate()->callback_token
            : $payout->merchant->user->api_access_token;

        $this->sendCallback($callbackUrl, $data, $token, $payout, CallbackLog::TYPE_PAYOUT);
    }

    private function sendCallback(string $url, array $payload, ?string $token, Model $model, string $type): void
    {
        $startedAt = microtime(true);
        $http = Http::withoutVerifying()->acceptJson();

        if ($token) {
            $http = $http->withHeader('Access-Token', $token);
        }

        try {
            $response = $http->post($url, $payload);
        } catch (Throwable $exception) {
            $this->recordCascadeMerchantPayoutCallbackLog(
                model: $model,
                url: $url,
                payload: $payload,
                responsePayload: ['message' => $exception->getMessage()],
                statusCode: null,
                startedAt: $startedAt,
                isSuccessful: false,
                errorCode: get_class($exception),
                errorMessage: $exception->getMessage(),
            );

            throw $exception;
        }

        $responsePayload = $response->json() ?: $response->body();

        try {
            $callbackLog = new CallbackLog([
                'type' => $type,
                'url' => $url,
                'request_data' => $payload,
                'response_data' => $responsePayload,
                'status_code' => $response->status(),
                'is_success' => $response->successful(),
            ]);

            $model->callbackLogs()->save($callbackLog);
        } catch (Throwable $exception) {
            report($exception);
        }

        $this->recordCascadeMerchantPayoutCallbackLog(
            model: $model,
            url: $url,
            payload: $payload,
            responsePayload: is_array($responsePayload) ? $responsePayload : ['body' => $responsePayload],
            statusCode: $response->status(),
            startedAt: $startedAt,
            isSuccessful: $response->successful(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $responsePayload
     */
    private function recordCascadeMerchantPayoutCallbackLog(
        Model $model,
        string $url,
        array $payload,
        array $responsePayload,
        ?int $statusCode,
        float $startedAt,
        bool $isSuccessful,
        ?string $errorCode = null,
        ?string $errorMessage = null,
    ): void {
        if (! $model instanceof Payout || $model->api_version !== 2) {
            return;
        }

        RecordCascadeMerchantLogJob::dispatch([
            'payout_id' => $model->id,
            'merchant_id' => $model->merchant_id,
            'payment_type' => CascadeMerchantLog::PAYMENT_TYPE_PAYOUT,
            'operation' => 'callback',
            'direction' => 'outgoing',
            'method' => 'POST',
            'url' => $url,
            'request_payload' => $payload,
            'response_payload' => $responsePayload,
            'status_code' => $statusCode,
            'execution_time' => round(microtime(true) - $startedAt, 4),
            'is_successful' => $isSuccessful,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);
    }
}
