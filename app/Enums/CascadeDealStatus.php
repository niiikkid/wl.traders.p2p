<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\Enumable;

enum CascadeDealStatus: string
{
    use Enumable;

    case SUCCESS = 'success';
    case FAIL = 'fail';
    case PENDING = 'pending';
}
