<?php

namespace App\DTO\Cascade;

use App\DTO\BaseDTO;
use App\Enums\CascadePaymentMethod;
use App\Services\Money\Money;

readonly class CreateCascadeDealDTO extends BaseDTO
{
    public const int DEFAULT_MAX_WAIT_MS = 30000;

    public const int MIN_MAX_WAIT_MS = 1000;

    public const int MAX_MAX_WAIT_MS = 30000;

    public function __construct(
        public int $merchantId,
        public string $externalId,
        public Money $amount,
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
        public int $maxWaitMs = self::DEFAULT_MAX_WAIT_MS,
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
            amount: Money::fromPrecision((string) $data['amount'], (string) $data['currency']),
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
            maxWaitMs: self::normalizeMaxWaitMs($data['max_wait_ms'] ?? null),
        );
    }

    public static function normalizeMaxWaitMs(mixed $value): int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return self::DEFAULT_MAX_WAIT_MS;
        }

        return max(self::MIN_MAX_WAIT_MS, min((int) $value, self::MAX_MAX_WAIT_MS));
    }
}
