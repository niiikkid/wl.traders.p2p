<?php

namespace App\Exceptions;

class InvoiceException extends BaseException
{
    public static function insufficientBalance(): static
    {
        return new self('Недостаточно средств на балансе.');
    }

    public static function unableToWithdrawByService(): static
    {
        return new self('Неизвестная ошибка. Обратитесь к администратору.');
    }

    public static function invalidInvoiceType(): static
    {
        return new self('Неверный тип инвойса.');
    }

    public static function onlyAutoWithdrawals(): static
    {
        return new self('Данный метод поддерживает только инвойсы для автовывода.');
    }

    public static function invoiceAlreadyFinished(): static
    {
        return new self('Инвойс уже завершен.');
    }

    public static function invoiceAlreadyExists(): static
    {
        return new self('Инвойс с таким transaction id уже существует.');
    }
}
