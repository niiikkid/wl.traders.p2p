<?php

namespace App\Enums;

use App\Traits\Enumable;

enum TelegramChatMessageStatus: string
{
    use Enumable;

    case RECEIVED = 'received';
    case IGNORED = 'ignored';
    case MATCHED = 'matched';
    case PROCESSED = 'processed';
    case FAILED = 'failed';
    case DUPLICATE = 'duplicate';
}
