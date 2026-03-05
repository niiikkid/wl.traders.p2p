<?php

namespace App\Exceptions;

class FundsHolderException extends BaseException
{
    public static function invalidAmountCurrency(): FundsHolderException
    {
        return new self('Неподдерживаемая валюта.');
    }

    public static function invalidStatus(): FundsHolderException
    {
        return new self('Действие не возможно для текущего статуса.');
    }

    public static function timerIsNotUpYet(): FundsHolderException
    {
        return new self('Таймер еще не истек.');
    }
}
