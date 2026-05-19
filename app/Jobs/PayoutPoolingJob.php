<?php

namespace App\Jobs;

use App\DTO\Payout\PayoutCreateDTO;
use App\Enums\PayoutMethodType;
use App\Exceptions\PayoutCreationTimedOutException;
use App\Exceptions\PayoutException;
use App\Models\Merchant;
use App\Models\PaymentGateway;
use App\Services\Money\Money;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PayoutPoolingJob implements ShouldQueue
{
    use Queueable;

    public int $timeout;

    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $jobID,
        public int $createdAtMs,
        public array $payload,
        public int $maxWaitMs,
        public int $creationDeadlineAtMs,
    ) {
        $this->timeout = max(5, min(120, (int) ceil($maxWaitMs / 1000) + 5));
        $this->onQueue('payout-pooling');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $job = $this->readState();

            if (! $job || ($job['status'] ?? null) !== 'queued') {
                return;
            }

            if ($this->isCreationDeadlineExceeded()) {
                $this->writeState(['status' => 'expired']);

                return;
            }

            $this->writeState(['status' => 'processing']);

            $merchant = queries()->merchant()->findByUUID($this->payload['merchant_id']);
            if (! $merchant instanceof Merchant) {
                throw new PayoutException('Мерчант не найден.');
            }

            $paymentGateway = $this->resolvePaymentGateway();
            $currencyCode = $paymentGateway
                ? strtoupper($paymentGateway->currency->getCode())
                : strtoupper($this->payload['currency']);

            $payout = services()->payout()->create(
                PayoutCreateDTO::make(
                    merchant: $merchant,
                    paymentGateway: $paymentGateway,
                    externalId: $this->payload['external_id'],
                    amountFiat: Money::fromPrecision($this->payload['amount'], $currencyCode),
                    methodType: PayoutMethodType::from($this->payload['payout_method_type']),
                    requisites: $this->payload['requisites'],
                    initials: $this->payload['initials'],
                    currencyCode: $currencyCode,
                    callbackUrl: $this->payload['callback_url'] ?? null,
                    bankName: $this->payload['bank_name'] ?? null,
                    merchantRate: ! empty($this->payload['rate'])
                        ? Money::fromPrecision((string) $this->payload['rate'], $currencyCode)
                        : null,
                    creationDeadlineAtMs: $this->creationDeadlineAtMs,
                )
            );

            $this->writeState([
                'status' => 'done',
                'payout_id' => $payout->id,
            ]);
        } catch (PayoutCreationTimedOutException) {
            $this->writeState(['status' => 'expired']);
        } catch (Throwable $exception) {
            $this->writeState([
                'status' => 'failed',
                'exception' => [
                    'class' => get_class($exception),
                    'message' => $exception->getMessage(),
                ],
            ]);
        }
    }

    private function resolvePaymentGateway(): ?PaymentGateway
    {
        $gatewayCode = $this->payload['payment_gateway'] ?? null;

        if (! $gatewayCode) {
            return null;
        }

        return PaymentGateway::query()
            ->where('code', $gatewayCode)
            ->where('is_payouts_enabled', true)
            ->active()
            ->firstOrFail();
    }

    private function isCreationDeadlineExceeded(): bool
    {
        $nowMs = now()->getTimestampMs();

        return $nowMs >= $this->creationDeadlineAtMs
            || $nowMs - $this->createdAtMs >= $this->maxWaitMs;
    }

    private function readState(): ?array
    {
        $state = cache()->get($this->cacheKey());

        return $state ? json_decode($state, true) : null;
    }

    private function writeState(array $state): void
    {
        cache()->put($this->cacheKey(), json_encode($state), 60);
    }

    private function cacheKey(): string
    {
        return "payout:create:$this->jobID";
    }
}
