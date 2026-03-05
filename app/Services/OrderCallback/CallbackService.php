<?php

namespace App\Services\OrderCallback;

use App\Contracts\CallbackServiceContract;
use App\Http\Resources\API\Payout\PayoutCallbackResource;
use App\Models\CallbackLog;
use App\Models\Order;
use App\Models\Payout\Payout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

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
            $data = \App\Http\Resources\API\H2H\OrderResource::make($order)->resolve();
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

        $data = PayoutCallbackResource::make($payout)->resolve();
        $token = $payout->merchant->user->api_access_token;

        $this->sendCallback($callbackUrl, $data, $token, $payout, CallbackLog::TYPE_PAYOUT);
    }

    private function sendCallback(string $url, array $payload, ?string $token, Model $model, string $type): void
    {
        $http = Http::withoutVerifying()->acceptJson();

        if ($token) {
            $http = $http->withHeader('Access-Token', $token);
        }

        $response = $http->post($url, $payload);

        try {
            $callbackLog = new CallbackLog([
                'type' => $type,
                'url' => $url,
                'request_data' => $payload,
                'response_data' => $response->json() ?: $response->body(),
                'status_code' => $response->status(),
                'is_success' => $response->successful(),
            ]);

            $model->callbackLogs()->save($callbackLog);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
