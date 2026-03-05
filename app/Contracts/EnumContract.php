<?php

namespace App\Contracts;

interface EnumContract
{
    public static function values(): array;

    public static function options(): array;

    public function equals(string $value): bool;

    public function notEquals(string $value): bool;
}
