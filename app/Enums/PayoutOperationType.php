<?php

namespace App\Enums;

use App\Traits\Enumable;

enum PayoutOperationType: string
{
    use Enumable;

    case RESERVE_FROM_MERCHANT = 'reserve_from_merchant';
    case RETURN_TO_MERCHANT = 'return_to_merchant';
    case MARK_TAKEN = 'mark_taken';
    case MARK_SENT = 'mark_sent';
    case SET_HOLD = 'set_hold';
    case RELEASE_HOLD = 'release_hold';
    case CREDIT_TRADER = 'credit_trader';
    case SERVICE_INCOME = 'service_income';
    case TEAMLEAD_INCOME = 'teamlead_income';
}


