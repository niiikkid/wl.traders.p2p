<?php

namespace App\Enums;

use App\Traits\Enumable;

enum RateSourceDirection: string
{
    use Enumable;

    /**
     * Pay-in: merchant client pays fiat into a trader requisite (historically the "sell" side).
     */
    case PAY_IN = 'pay_in';

    /**
     * Pay-out: merchant pays fiat to a client via a trader (historically the "buy" side).
     */
    case PAY_OUT = 'pay_out';
}
