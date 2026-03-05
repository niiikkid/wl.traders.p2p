<?php

namespace App\Services\Wallet\ValueObjects;

class EscrowsValue extends ValueObject
{
    public function __construct(
        public EscrowValue $orders,
        public EscrowValue $disputes,
    )
    {}
}
