<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Enums\TransactionType;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpiresOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private Order $order,
    )
    {
        $this->afterCommit();
        $this->onQueue('order');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->order->status->equals(OrderStatus::PENDING) && ! $this->order->dispute) {
            services()->order()->finishOrderAsFailed($this->order->id, OrderSubStatus::EXPIRED);
        }
    }

    public function backoff(): array //3 попыток
    {
        return [30, 60, 180]; // Интервалы в секундах перед повторными попытками
    }
}
