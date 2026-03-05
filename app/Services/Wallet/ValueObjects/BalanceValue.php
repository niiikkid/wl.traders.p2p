<?php

namespace App\Services\Wallet\ValueObjects;

use App\Services\Money\Money;

class BalanceValue extends ValueObject
{
    public function __construct(
        public Money $primary,
        public Money $secondary,
    )
    {}
}
