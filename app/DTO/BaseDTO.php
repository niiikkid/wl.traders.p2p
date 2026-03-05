<?php

namespace App\DTO;

abstract readonly class BaseDTO
{
    public static function makeFromRequest(array $data): static
    {
        return make(static::class, $data);
    }
}
