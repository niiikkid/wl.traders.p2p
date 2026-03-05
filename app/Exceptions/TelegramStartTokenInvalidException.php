<?php

namespace App\Exceptions;

use Exception;

class TelegramStartTokenInvalidException extends Exception
{
    public function __construct()
    {
        parent::__construct('Telegram start token is invalid.');
    }
}
