<?php

namespace App\Services\Money\Utils;

use Illuminate\Support\Facades\Log;

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
        try {
            return bcdiv($amount, str_pad(1, $divisibility + 1, '0'), $divisibility);
        } catch (\Throwable $exception) {
            Log::error('FormatMoney::unitsToPrecision bcdiv failed', [
                'amount' => $amount,
                'amount_type' => get_debug_type($amount),
                'amount_length' => mb_strlen($amount),
                'divisibility' => $divisibility,
                'divisor' => str_pad(1, $divisibility + 1, '0'),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
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

}
