<?php

namespace App\Jobs;

use App\Contracts\OrderServiceContract;
use App\DTO\Order\CreateOrderDTO;
use App\Exceptions\OrderException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class OrderPoolingJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 5;
    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $jobID,
        public int $createdAt,
        public array $payload,
        public int $maxWaitMs,
    )
    {
        $this->afterCommit();
        $this->onQueue('order-pooling');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $job = cache()->get("order:create:$this->jobID");

            if (!$job) {
                return;
            }

            $job = json_decode($job, true);

            if (empty($job['status']) || $job['status'] !== 'queued') {
                return;
            }

            if (now()->getTimestampMs() - $this->createdAt > $this->maxWaitMs) {
                cache()->put("order:create:$this->jobID", json_encode([
                    'status' => 'expired',
                ]), 60);
                return;
            }

            cache()->put("order:create:$this->jobID", json_encode([
                'status' => 'processing',
            ]), 60);

            $merchant = queries()->merchant()->findByUUID($this->payload['merchant_id']);

            $order = make(OrderServiceContract::class)->create(
                CreateOrderDTO::makeFromRequest($this->payload + ['merchant' => $merchant])
            );

            cache()->put("order:create:$this->jobID", json_encode([
                'status' => 'done',
                'order_id' => $order->id,
            ]), 60);
        } catch (OrderException|Throwable $e) {
            cache()->put("order:create:$this->jobID", json_encode([
                'status' => 'failed',
                'exception' => [
                    'class' => get_class($e),
                    'message' => $e->getMessage(),
                ],
            ]), 60);
        }
    }
}
