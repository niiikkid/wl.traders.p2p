<?php

namespace App\Services\OrderCallback;

use App\Contracts\CallbackServiceContract;
use App\Http\Resources\API\H2H\OrderResource;
use App\Http\Resources\API\Payout\PayoutResource;
use App\Models\CallbackLog;
use App\Models\Order;
use App\Models\Payout\Payout;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use JsonException;
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

        $data = OrderResource::make($order)->resolve();

        $this->sendCallback($callback_url, $data, $order->merchant->user, $order, CallbackLog::TYPE_ORDER);
    }

    public function sendForPayout(Payout $payout, ?int $callbackRevision = null): void
    {
        $payout->load(['merchant.user', 'paymentGateway', 'trader']);

        $callbackUrl = $payout->callback_url
            ?? $payout->merchant->payout_callback_url;

        if (! $callbackUrl) {
            return;
        }

        $data = PayoutResource::make($payout)->resolve();
        $this->sendCallback(
            url: $callbackUrl,
            payload: $data,
            user: $payout->merchant->user,
            model: $payout,
            type: CallbackLog::TYPE_PAYOUT,
        );
    }

    private function sendCallback(
        string $url,
        array $payload,
        User $user,
        Model $model,
        string $type,
    ): bool {
        try {
            $jsonPayload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            report($exception);

            return false;
        }

        $http = Http::withoutVerifying()
            ->acceptJson()
            ->withBody($jsonPayload, 'application/json');

        $signature = $user->signWebhookPayload($jsonPayload);

        if ($signature) {
            $http = $http->withHeaders([
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Signature-Algorithm' => 'HMAC-SHA256',
            ]);
        }

        $response = $http->post($url);

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

        return $response->successful();
    }
}
