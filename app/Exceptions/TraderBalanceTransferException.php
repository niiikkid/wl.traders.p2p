<?php

declare(strict_types=1);

namespace App\Exceptions;

class TraderBalanceTransferException extends BaseException
{
    public static function recipientNotAvailable(): static
    {
        return new self('Трейдер не найден или недоступен для перевода.');
    }

    public static function insufficientTrustBalance(string $availableAmount): static
    {
        return new self(sprintf(
            'Недостаточно средств на рабочем балансе. Доступно: %s USDT.',
            $availableAmount,
        ));
    }

    public static function transferUnavailable(): static
    {
        return new self('Перевод недоступен.');
    }
}
