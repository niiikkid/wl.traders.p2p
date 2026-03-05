<?php

namespace App\Services\Sms\Utils;

class NormalizeMessage
{
    public static function normalize(string $message): string
    {
        $message = str_replace("\u{A0}", ' ', $message);
        $message = str_replace("\r\n", ' ', $message);
        $message = str_replace("\r", ' ', $message);
        $message = str_replace("\n", ' ', $message);
        $message = trim($message);
        return mb_strtolower($message);
    }
}
