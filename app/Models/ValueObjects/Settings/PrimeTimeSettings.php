<?php

namespace App\Models\ValueObjects\Settings;

class PrimeTimeSettings
{
    public function __construct(
        public string $starts,
        public string $ends,
        public float $rate
    )
    {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
