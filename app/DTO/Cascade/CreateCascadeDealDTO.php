<?php

namespace App\DTO\Cascade;

use App\DTO\BaseDTO;
use App\Enums\CascadePaymentMethod;

readonly class CreateCascadeDealDTO extends BaseDTO
{
    public function __construct(
        public int $merchantId,
        public string $externalId,
        public int $amount,
        public string $currency,
        public CascadePaymentMethod $paymentMethod,
        public ?string $callbackUrl = null,
        public ?string $clientId = null,
    ) {}

    public static function makeFromRequest(array $data): static
    {
        return new static(
            merchantId: (int) $data['merchant_id'],
            externalId: (string) $data['external_id'],
            amount: (int) $data['amount'],
            currency: (string) $data['currency'],
            paymentMethod: CascadePaymentMethod::from((string) $data['payment_method']),
            callbackUrl: $data['callback_url'] ?? null,
            clientId: $data['client_id'] ?? null,
        );
    }
}
