<?php

namespace App\Exceptions;

use Exception;

abstract class BaseException extends Exception
{
    public static function make(string $message): static
    {
        return new static($message);
    }
}
