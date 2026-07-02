<?php

namespace App\Enums;

use App\Traits\Enumable;

enum WalletDepositMatchType: string
{
    use Enumable;

    case AUTOMATIC = 'automatic';
    case MANUAL = 'manual';
}
