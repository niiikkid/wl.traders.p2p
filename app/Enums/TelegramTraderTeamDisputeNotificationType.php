<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\Enumable;

enum TelegramTraderTeamDisputeNotificationType: string
{
    use Enumable;

    case IMMEDIATE = 'immediate';
    case FIFTEEN_MINUTE_REMINDER = 'fifteen_minute';
    case HOURLY_REMINDER = 'hourly';
}
