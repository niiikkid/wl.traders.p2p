<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CascadeDealEventType;
use App\Http\Resources\API\V2\OrderResource;
use App\Models\CallbackLog;
use App\Models\CascadeDeal;
use App\Services\Cascade\CascadeDealEventRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Throwable;

class SendCascadeDealCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 8;

    public int $timeout = 10;

    private const LOCK_TTL = 120;

    public function __construct(private readonly CascadeDeal $cascadeDeal)
    {
        $this->onQueue('callback');
        $this->afterCommit();
    }

    public function handle(CascadeDealEventRecorder $events): void
    {
        $deal = $this->cascadeDeal->fresh(['merchant.apiCredential']);

        if (! $deal || ! $deal->callback_url || ! $deal->isVisibleInMerchantApi()) {
            return;
        }

        $lockKey = 'cascade_deal_callback_lock:'.$deal->id;

        if (! Redis::set($lockKey, 1, 'EX', self::LOCK_TTL, 'NX')) {
            $this->release(10);

            return;
        }

        try {
            $started_at = microtime(true);
            $payload = OrderResource::make($deal)->resolve();
            $http = Http::withoutVerifying()->acceptJson()->timeout(10);
            $token = $deal->merchant->apiCredentialOrCreate()->callback_token;

            if ($token) {
                $http = $http->withHeader('Access-Token', $token);
            }

            $response = $http->post($deal->callback_url, $payload);
            $response_payload = $response->json() ?: $response->body();

            $callbackLog = new CallbackLog([
                'type' => CallbackLog::TYPE_CASCADE_PAYIN,
                'url' => $deal->callback_url,
                'request_data' => $payload,
                'response_data' => $response_payload,
                'status_code' => $response->status(),
                'is_success' => $response->successful(),
            ]);

            $deal->callbackLogs()->save($callbackLog);
            RecordCascadeMerchantLogJob::dispatch([
                'cascade_deal_id' => $deal->id,
                'merchant_id' => $deal->merchant_id,
                'operation' => 'callback',
                'direction' => 'outgoing',
                'method' => 'POST',
                'url' => $deal->callback_url,
                'request_payload' => $payload,
                'response_payload' => is_array($response_payload) ? $response_payload : ['body' => $response_payload],
                'status_code' => $response->status(),
                'execution_time' => round(microtime(true) - $started_at, 4),
                'is_successful' => $response->successful(),
            ]);

            $events->record(
                deal: $deal,
                type: CascadeDealEventType::CALLBACK_SENT,
                payload: [
                    'url' => $deal->callback_url,
                    'status_code' => $response->status(),
                    'is_success' => $response->successful(),
                ],
            );
        } catch (Throwable $e) {
            if (isset($deal, $payload, $started_at)) {
                try {
                    $callbackLog = new CallbackLog([
                        'type' => CallbackLog::TYPE_CASCADE_PAYIN,
                        'url' => $deal->callback_url,
                        'request_data' => $payload,
                        'response_data' => ['message' => $e->getMessage()],
                        'status_code' => null,
                        'is_success' => false,
                    ]);

                    $deal->callbackLogs()->save($callbackLog);
                } catch (Throwable $logException) {
                    report($logException);
                }

                RecordCascadeMerchantLogJob::dispatch([
                    'cascade_deal_id' => $deal->id,
                    'merchant_id' => $deal->merchant_id,
                    'operation' => 'callback',
                    'direction' => 'outgoing',
                    'method' => 'POST',
                    'url' => $deal->callback_url,
                    'request_payload' => $payload,
                    'response_payload' => ['message' => $e->getMessage()],
                    'status_code' => null,
                    'execution_time' => round(microtime(true) - $started_at, 4),
                    'is_successful' => false,
                    'error_code' => get_class($e),
                    'error_message' => $e->getMessage(),
                ]);
            }

            throw $e;
        } finally {
            Redis::del($lockKey);
        }
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 120, 240, 480, 1800, 3600, 7200];
    }
}
