<?php

namespace App\Exceptions;

use Exception;

class TelegramAccountNotLinkedException extends Exception
{
    public function __construct()
    {
        parent::__construct('Telegram account is not linked.');
    }
}
