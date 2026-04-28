<?php

namespace App\Exceptions;

class WalletException extends BaseException
{
    public static function invalidTransactionTypeForTake()
    {
        return static::make('Неверный тип транзакции для вывода средств из кошелька.');
    }

    public static function invalidTransactionTypeForGive()
    {
        return static::make('Неверный тип транзакции для зачисления на кошелек.');
    }

    public static function insufficientFunds()
    {
        return static::make('Недостаточно средств на балансе.');
    }
}
