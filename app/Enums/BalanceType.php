<?php

namespace App\Enums;

use App\Traits\Enumable;

enum BalanceType: string
{
    use Enumable;

    case TRUST = 'trust';
    case PROVIDER = 'provider';
    case MERCHANT = 'merchant';
    case COMMISSION = 'commission';
    case TEAMLEADER = 'teamleader';
}
