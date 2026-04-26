<?php

declare(strict_types=1);

namespace App\Models\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
readonly class CascadeManualControl implements Arrayable
{
    public function __construct(
        public ?string $cardNumber = null,
        public ?int $expiryMonth = null,
        public ?int $expiryYear = null,
        public ?string $cvc = null,
        public ?string $cardholderName = null,
    ) {}

    public static function make(
        bool $manualControlAcquiring = false,
        ?string $cardNumber = null,
        ?int $expiryMonth = null,
        ?int $expiryYear = null,
        ?string $cvc = null,
        ?string $cardholderName = null,
    ): ?self {
        $has_manual_control_data = $cardNumber !== null
            || $expiryMonth !== null
            || $expiryYear !== null
            || $cvc !== null
            || $cardholderName !== null;

        if (! $manualControlAcquiring && ! $has_manual_control_data) {
            return null;
        }

        return new self(
            cardNumber: $cardNumber,
            expiryMonth: $expiryMonth,
            expiryYear: $expiryYear,
            cvc: $cvc,
            cardholderName: $cardholderName,
        );
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        return self::make(
            manualControlAcquiring: true,
            cardNumber: isset($data['card_number']) ? (string) $data['card_number'] : null,
            expiryMonth: isset($data['expiry_month']) ? (int) $data['expiry_month'] : null,
            expiryYear: isset($data['expiry_year']) ? (int) $data['expiry_year'] : null,
            cvc: isset($data['cvc']) ? (string) $data['cvc'] : null,
            cardholderName: isset($data['cardholder_name']) ? (string) $data['cardholder_name'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'card_number' => $this->cardNumber,
            'expiry_month' => $this->expiryMonth,
            'expiry_year' => $this->expiryYear,
            'cvc' => $this->cvc,
            'cardholder_name' => $this->cardholderName,
        ];
    }
}
