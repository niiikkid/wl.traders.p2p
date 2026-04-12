<?php

namespace App\Enums;

use App\Traits\Enumable;

enum ManualControlProcessingStatus: string
{
    use Enumable;

    case PENDING = 'pending';
    case REJECTED = 'rejected';
    case CONFIRMED = 'confirmed';

    public function title(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::REJECTED => 'Rejected',
            self::CONFIRMED => 'Confirmed',
        };
    }
}
