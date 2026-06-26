<?php

declare(strict_types=1);

namespace App\Exceptions;

class OrderSmsLogException extends BaseException
{
    public static function orderAlreadyHasSms(): self
    {
        return self::make('К сделке уже привязано сообщение.');
    }

    public static function smsLogNotAvailable(): self
    {
        return self::make('Сообщение недоступно для привязки.');
    }

    public static function smsLogAlreadyRejected(): self
    {
        return self::make('Сообщение уже отклонено.');
    }

    public static function smsLogAlreadyLinked(): self
    {
        return self::make('К сообщению уже привязана сделка.');
    }
}
