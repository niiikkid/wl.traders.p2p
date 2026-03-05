<?php

namespace App\Utils;

use Closure;
use Illuminate\Support\Facades\DB;

class Transaction
{
    public static function run(Closure $callback)
    {
        if (DB::transactionLevel() > 0) {
            // Если уже есть активная транзакция, просто выполняем код
            return $callback();
        }

        // Иначе создаём новую транзакцию
        return DB::transaction($callback);
    }
}
