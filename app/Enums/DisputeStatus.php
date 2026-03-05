<?php

namespace App\Enums;

use App\Traits\Enumable;

enum DisputeStatus: string
{
    use Enumable;

    case ACCEPTED = 'accepted';
    case CANCELED = 'canceled';
    case PENDING = 'pending';
}
