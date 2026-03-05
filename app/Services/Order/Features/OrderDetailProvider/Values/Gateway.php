<?php

namespace App\Services\Order\Features\OrderDetailProvider\Values;

class Gateway
{
    public function __construct(
        public int $id,
        public string $code,
        public int $reservationTime,
        public float $serviceCommissionRate,
        public float $traderCommissionRate,
    )
    {}
}
