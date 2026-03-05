<?php

namespace App\Jobs;

use App\Models\Merchant;
use App\Models\MerchantApiRequestLog;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class UpdateMerchantApiLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private int $merchantId,
        private string $requestId,
        private array $responseData,
        private bool $isSuccessful,
        private ?string $errorMessage = null,
        private ?int $orderId = null,
        private ?string $exceptionClass = null,
        private ?string $exceptionMessage = null,
        private ?float $executionTime = null,
    ) {
        $this->afterCommit();
        $this->onQueue('logging');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Ищем запись лога по merchant_id и request_id
        $log = MerchantApiRequestLog::where('merchant_id', $this->merchantId)
            ->where('request_id', $this->requestId)
            ->first();

        // Если запись не найдена, возвращаем джоб в очередь для повторной попытки
        if (!$log) {
            // Если это не последняя попытка, возвращаем джоб в очередь
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff()[$this->attempts() - 1]);
                return;
            }
            return;
        }

        // Обновляем запись лога
        $log->update([
            'order_id' => $this->orderId,
            'response_data' => $this->responseData,
            'is_successful' => $this->isSuccessful,
            'error_message' => $this->errorMessage,
            'exception_class' => $this->exceptionClass,
            'exception_message' => $this->exceptionMessage,
            'execution_time' => $this->executionTime,
        ]);
    }

    /**
     * Определяет интервалы между повторными попытками
     */
    public function backoff(): array
    {
        return [5, 15, 30]; // Интервалы в секундах перед повторными попытками
    }
}
