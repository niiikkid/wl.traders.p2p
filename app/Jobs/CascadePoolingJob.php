<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Exceptions\CascadeException;
use App\Models\CascadeDeal;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CascadePoolingJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 5;
    public int $tries = 1;

    public function __construct(
        public string $job_id,
        public int $created_at,
        public array $payload,
        public int $max_wait_ms,
    ) {
        $this->afterCommit();
        $this->onQueue('cascade-pooling');
    }

    public function handle(): void
    {
        try {
            $job = cache()->get("cascade:deal:create:$this->job_id");

            if (! $job) {
                return;
            }

            $job = json_decode($job, true);

            if (empty($job['status']) || $job['status'] !== 'queued') {
                return;
            }

            if (now()->getTimestampMs() - $this->created_at > $this->max_wait_ms) {
                cache()->put("cascade:deal:create:$this->job_id", json_encode([
                    'status' => 'expired',
                ]), 60);
                return;
            }

            cache()->put("cascade:deal:create:$this->job_id", json_encode([
                'status' => 'processing',
            ]), 60);

           /* $cascade_deal = CascadeDeal::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'external_id' => $this->payload['external_id'],
                'merchant_id' => $this->payload['merchant_id'],
                'amount' => $this->payload['amount'],
                'initial_amount' => $this->payload['amount'],
                'currency' => $this->payload['currency'],
                'payment_method' => $this->payload['payment_method'],
                'status' => OrderStatus::PENDING,
                'sub_status' => OrderSubStatus::WAITING_FOR_DETAILS_TO_BE_SELECTED,
                'callback_url' => $this->payload['callback_url'] ?? null,
            ]);*/

            // TODO: здесь будет запуск каскадной логики создания сделки у провайдера

            cache()->put("cascade:deal:create:$this->job_id", json_encode([
                'status' => 'done',
                'cascade_deal_id' => 1//$cascade_deal->id,
            ]), 60);
        } catch (CascadeException|Throwable $e) {
            cache()->put("cascade:deal:create:$this->job_id", json_encode([
                'status' => 'failed',
                'exception' => [
                    'class' => get_class($e),
                    'message' => $e->getMessage(),
                ],
            ]), 60);
        }
    }
}
