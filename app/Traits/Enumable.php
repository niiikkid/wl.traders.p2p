<?php

namespace App\Traits;

trait Enumable
{
    public static function values()
    {
        $cases = static::cases();

        return array_map(fn ($case) =>  $case->value, $cases);
    }

    public static function options()
    {
        $cases = static::cases();

        $options = [];

        foreach ($cases as $case) {
            $options[$case->value] = $case->value;
        }

        return $options;
    }

    public function equals(string | self | null $value): bool
    {
        if (empty($value)) {
            return false;
        }

        if (is_string($value)) {
            return $this->value === $value;
        }

        return $this->value === $value->value;
    }

    public function notEquals(string | self | null $value): bool
    {
        if (empty($value)) {
            return true;
        }

        if (is_string($value)) {
            return $this->value !== $value;
        }

        return $this->value !== $value->value;
    }

    public function notEqualsAny(array $values): bool
    {
        foreach ($values as $value) {
            if ($this->notEquals($value)) {
                return false;
            }
        }

        return true;
    }
}
