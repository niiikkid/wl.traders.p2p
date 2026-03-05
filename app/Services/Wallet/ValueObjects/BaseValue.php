<?php

namespace App\Services\Wallet\ValueObjects;

use App\Services\Money\Money;

class BaseValue extends ValueObject
{
    public function __construct(
        public Money $merchantAmount,
        public Money $trustAmount,
        public Money $trustReserveAmount,
        public Money $teamleaderAmount,
    )
    {}
}
