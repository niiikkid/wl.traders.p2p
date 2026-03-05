<?php

namespace App\Enums;

use App\Traits\Enumable;

enum InvoiceType: string
{
    use Enumable;

    case DEPOSIT = 'deposit';
    case WITHDRAWAL = 'withdrawal';
}
