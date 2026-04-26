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
        public ?string $rate = null,
        public bool $manualControlAcquiring = false,
        public ?string $cardNumber = null,
        public ?int $expiryMonth = null,
        public ?int $expiryYear = null,
        public ?string $cvc = null,
        public ?string $cardholderName = null,
    ) {}

    public static function makeFromRequest(array $data): static
    {
        $rate = $data['rate'] ?? null;
        if ($rate === '' || $rate === null) {
            $rate = null;
        } else {
            $rate = is_string($rate) ? $rate : (string) $rate;
        }

        return new static(
            merchantId: (int) $data['merchant_id'],
            externalId: (string) $data['external_id'],
            amount: (int) $data['amount'],
            currency: (string) $data['currency'],
            paymentMethod: CascadePaymentMethod::from((string) $data['payment_method']),
            callbackUrl: $data['callback_url'] ?? null,
            clientId: $data['client_id'] ?? null,
            rate: $rate,
            manualControlAcquiring: (bool) ($data['manual_control_acquiring'] ?? false),
            cardNumber: isset($data['card_number']) ? (string) $data['card_number'] : null,
            expiryMonth: isset($data['expiry_month']) ? (int) $data['expiry_month'] : null,
            expiryYear: isset($data['expiry_year']) ? (int) $data['expiry_year'] : null,
            cvc: isset($data['cvc']) ? (string) $data['cvc'] : null,
            cardholderName: isset($data['cardholder_name']) ? (string) $data['cardholder_name'] : null,
        );
    }
}
