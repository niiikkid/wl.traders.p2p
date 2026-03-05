<?php

namespace App\Services\Sms\ValueObjects;

use App\Models\PaymentGateway;
use App\Services\Money\Money;

class ParserResultValue
{
    public function __construct(
        public Money $amount,
        public PaymentGateway $paymentGateway,
        public ?string $card_last_digits,
    )
    {}
}
