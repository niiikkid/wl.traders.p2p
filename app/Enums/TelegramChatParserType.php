<?php

namespace App\Enums;

use App\Traits\Enumable;

enum TelegramChatParserType: string
{
    use Enumable;

    case STANDARD_DISPUTE = 'standard_dispute';
}
