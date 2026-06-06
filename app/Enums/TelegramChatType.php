<?php

namespace App\Enums;

use App\Traits\Enumable;

enum TelegramChatType: string
{
    use Enumable;

    case DISPUTE_PROCESSING = 'dispute_processing';
    case TRADER_TEAM = 'trader_team';
}
