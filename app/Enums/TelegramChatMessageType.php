<?php

namespace App\Enums;

use App\Traits\Enumable;

enum TelegramChatMessageType: string
{
    use Enumable;

    case TEXT = 'text';
    case PHOTO = 'photo';
    case DOCUMENT = 'document';
    case UNKNOWN = 'unknown';
}
