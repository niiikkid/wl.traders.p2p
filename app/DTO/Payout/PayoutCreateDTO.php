<?php

namespace App\DTO\Payout;

use App\DTO\BaseDTO;
use App\Enums\PayoutMethodType;
use App\Models\Merchant;
use App\Models\PaymentGateway;
use App\Services\Money\Money;

readonly class PayoutCreateDTO extends BaseDTO
{
    public function __construct(
        public Merchant $merchant,
        public ?PaymentGateway $paymentGateway,
        public ?string $externalId,
        public Money $amountFiat,
        public PayoutMethodType $methodType,
        public string $requisites,
        public ?string $initials,
        public string $currencyCode,
        public ?string $callbackUrl,
        public ?string $bankName,
    ) {
    }

    public static function make(
        Merchant $merchant,
        ?PaymentGateway $paymentGateway,
        ?string $externalId,
        Money $amountFiat,
        PayoutMethodType $methodType,
        string $requisites,
        ?string $initials,
        string $currencyCode,
        ?string $callbackUrl,
        ?string $bankName,
    ): self {
        return new self(
            merchant: $merchant,
            paymentGateway: $paymentGateway,
            externalId: $externalId,
            amountFiat: $amountFiat,
            methodType: $methodType,
            requisites: $requisites,
            initials: $initials,
            currencyCode: $currencyCode,
            callbackUrl: $callbackUrl,
            bankName: $bankName,
        );
    }
}

