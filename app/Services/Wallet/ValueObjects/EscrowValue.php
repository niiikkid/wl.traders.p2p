<?php

namespace App\Services\Wallet\ValueObjects;

class EscrowValue extends ValueObject
{
    public function __construct(
        public BalanceValue $balance,
        public int $count,
    )
    {}
}
