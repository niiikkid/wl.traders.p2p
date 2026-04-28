<?php

namespace App\Exceptions;

class WalletException extends BaseException
{
    public static function invalidTransactionTypeForTake()
    {
        return make('Неверный тип транзакции для вывода средств из кошелька.');
    }

    public static function invalidTransactionTypeForGive()
    {
        return make('Неверный тип транзакции для зачисления на кошелек.');
    }

    public static function insufficientFunds()
    {
        return make('Недостаточно средств на балансе.');
    }
}
