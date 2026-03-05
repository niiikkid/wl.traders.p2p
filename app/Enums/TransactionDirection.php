<?php

namespace App\Enums;

use App\Traits\Enumable;

enum TransactionDirection: string
{
    use Enumable;

    case IN = 'in';
    case OUT = 'out';
}
