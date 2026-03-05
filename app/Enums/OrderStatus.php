<?php

namespace App\Enums;

use App\Traits\Enumable;

enum OrderStatus: string
{
    use Enumable;

    case SUCCESS = 'success';
    case FAIL = 'fail';
    case PENDING = 'pending';
}
