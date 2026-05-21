<?php

namespace App\Enums;

use App\Traits\Enumable;

enum TelegramChatStatus: string
{
    use Enumable;

    case PENDING_MODERATION = 'pending_moderation';
    case ACTIVE = 'active';
    case DISABLED = 'disabled';
    case ARCHIVED = 'archived';
}
