<?php

namespace App\Enums;

use App\Traits\Enumable;

enum SmsType: string
{
    use Enumable;

    case SMS = 'sms';
    case PUSH = 'push';
}
