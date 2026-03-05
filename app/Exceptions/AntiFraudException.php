<?php

namespace App\Exceptions;

class AntiFraudException extends OrderException
{
    public static function clientIdRequired(): self
    {
        return new self('Не указан client_id для антифрод-проверки.');
    }

    public static function blockedUntil(string $until): self
    {
        return new self("Клиент заблокирован до {$until}.");
    }

    public static function maxPendingExceeded(int $current, int $limit): self
    {
        return new self("Превышен лимит активных сделок: {$current} из {$limit}.");
    }

    public static function rateLimitExceeded(int $current, int $limit, int $minutes): self
    {
        return new self("Превышен лимит: {$current} из {$limit} сделок за {$minutes} минут.");
    }

    public static function failedLimitExceeded(int $current, int $limit): self
    {
        return new self("Превышен лимит подряд неуспешных сделок: {$current} из {$limit}.");
    }
}
