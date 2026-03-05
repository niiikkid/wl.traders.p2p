<?php

namespace App\Enums;

use App\Traits\Enumable;

enum PayoutMethodType: string
{
    use Enumable;

    case SBP = 'sbp';
    case CARD = 'card';
}


