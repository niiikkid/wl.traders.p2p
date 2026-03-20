<?php

namespace App\Services\Money\Utils;

class FormatMoney
{
    public static function precisionToUnits(string $amount, int $divisibility): string
    {
        $is_minus = $amount[0] === '-';

        if ($is_minus) {
            $amount = ltrim($amount, '-');
        }

        $parts = explode('.', $amount);

        $part = !empty($parts[1]) ? $parts[1] : '';

        $part = mb_strimwidth(str_pad($part, $divisibility, '0'), 0, $divisibility);

        $amount = $parts[0] . $part;

        $amount = ltrim($amount, '0');

        $amount = empty($amount) ? '0' : $amount;

        if ($is_minus) {
            $amount = '-' . $amount;
        }

        return $amount;
    }

    public static function unitsToPrecision(string $amount, int $divisibility): string
    {
        if (str_contains($amount, 'e') || str_contains($amount, 'E')) {
            $amount = self::scientificToDecimal($amount);
        }

        return bcdiv($amount, str_pad(1, $divisibility + 1, '0'), $divisibility);
    }

    public static function beautifyPrecision(string $amount): string
    {
        if (! str_contains($amount, '.')) {
            return $amount;
        }

        $amount = rtrim($amount, '0');
        $amount = rtrim($amount, '.');

        return !empty($amount) ? $amount : 0;
    }

    private static function scientificToDecimal(string $amount): string
    {
        if (! preg_match('/^([+-]?)(\d+)(?:\.(\d+))?[eE]([+-]?\d+)$/', $amount, $matches)) {
            return $amount;
        }

        $sign = $matches[1] === '-' ? '-' : '';
        $integer_part = $matches[2];
        $fraction_part = $matches[3] ?? '';
        $exponent = (int) $matches[4];

        $digits = ltrim($integer_part . $fraction_part, '0');

        if ($digits === '') {
            return '0';
        }

        $decimal_position = strlen($integer_part) + $exponent;

        if ($decimal_position <= 0) {
            $result = '0.' . str_repeat('0', abs($decimal_position)) . $digits;
        } elseif ($decimal_position >= strlen($digits)) {
            $result = $digits . str_repeat('0', $decimal_position - strlen($digits));
        } else {
            $result = substr($digits, 0, $decimal_position) . '.' . substr($digits, $decimal_position);
        }

        if (str_contains($result, '.')) {
            [$left, $right] = explode('.', $result, 2);
            $left = ltrim($left, '0');
            $left = $left === '' ? '0' : $left;
            $right = rtrim($right, '0');
            $result = $right === '' ? $left : $left . '.' . $right;
        } else {
            $result = ltrim($result, '0');
            $result = $result === '' ? '0' : $result;
        }

        return $result === '0' ? '0' : $sign . $result;
    }

}
