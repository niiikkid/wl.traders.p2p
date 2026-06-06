<?php

declare(strict_types=1);

namespace App\Support;

final class TelegramUsernameNormalizer
{
    public const VALIDATION_PATTERN = '/^@?[A-Za-z0-9_]{5,32}$/';

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return ltrim($value, '@');
    }
}
