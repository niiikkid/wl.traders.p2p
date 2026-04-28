<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\Enumable;

enum CascadeDisputeStatus: string
{
    use Enumable;

    case OPENED = 'opened';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
}
