<?php

namespace App\Exceptions;

class WalletDepositException extends BaseException
{
    public static function invalidAmount(): static
    {
        return new self('Сумма пополнения должна быть положительным целым числом.');
    }

    public static function noActiveAddressAvailable(): static
    {
        return new self('Нет доступного адреса для этой суммы. Попробуйте другую сумму или повторите позже.');
    }

    public static function invoiceAlreadyFinal(): static
    {
        return new self('Инвойс уже завершён и не может быть изменён.');
    }

    public static function transferNotFound(): static
    {
        return new self('Транзакция не найдена для адреса этого инвойса.');
    }

    public static function transferAlreadyAttached(): static
    {
        return new self('Эта транзакция уже привязана к другому инвойсу.');
    }

    public static function recipientMismatch(): static
    {
        return new self('Получатель транзакции не совпадает с адресом инвойса.');
    }

    public static function contractMismatch(): static
    {
        return new self('Токен транзакции не является ожидаемым USDT (TRC20).');
    }

    public static function amountMismatch(): static
    {
        return new self('Сумма транзакции не совпадает с суммой инвойса.');
    }

    public static function addressAlreadyExists(): static
    {
        return new self('Такой адрес уже есть в пуле.');
    }
}
