<?php

namespace App\Enums;

use App\Traits\Enumable;

enum DetailType: string
{
    use Enumable;

    case CARD = 'card';
    case PHONE = 'phone';
    case MOBILE_COMMERCE = 'mobile_commerce';
    case ACCOUNT_NUMBER = 'account_number';
    case NSPK = 'nspk';
}
