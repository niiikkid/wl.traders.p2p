<?php

namespace App\Enums;

use App\Traits\Enumable;

enum FundsOnHoldStatus: string
{
    use Enumable;

    case TIMER_NOT_SET = 'timer_not_set';
    case PENDING_FOR_EXECUTION = 'pending_for_execution';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';
}
