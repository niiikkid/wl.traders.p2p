<?php

namespace App\Exceptions;

use RuntimeException;

class PayoutCreationTimedOutException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Не удалось обработать запрос вовремя.');
    }
}
