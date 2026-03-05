<?php

namespace App\Enums;

use App\Traits\Enumable;

enum InvoiceStatus: string
{
    use Enumable;

    case SUCCESS = 'success';
    case FAIL = 'fail';
    case PENDING = 'pending';
}
