<?php

namespace App\Jobs;

use App\Models\Merchant;
use App\Models\MerchantApiRequestLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateMerchantApiLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private Merchant $merchant,
        private array $requestData,
        private string $requestId,
        private ?string $ipAddress = null,
        private ?string $userAgent = null,
    ) {
        $this->afterCommit();
        $this->onQueue('logging');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        MerchantApiRequestLog::create([
            'request_id' => $this->requestId,
            'external_id' => $this->requestData['external_id'] ?? null,
            'amount' => $this->requestData['amount'] ?? null,
            'currency' => $this->requestData['currency'] ?? null,
            'payment_gateway' => $this->requestData['payment_gateway'] ?? null,
            'payment_detail_type' => $this->requestData['payment_detail_type'] ?? null,
            'request_data' => $this->requestData,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'is_successful' => false, // По умолчанию считаем неуспешным, обновим после ответа
            'merchant_id' => $this->merchant->id,
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
