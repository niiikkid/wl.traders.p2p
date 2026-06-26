<?php

declare(strict_types=1);

namespace App\Services\Sms\AutoClose;

enum CardMatchResult
{
    case Matched;
    case Mismatched;
    case Unknown;
}
