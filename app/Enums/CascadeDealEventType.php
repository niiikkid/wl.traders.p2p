<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\Enumable;

enum CascadeDealEventType: string
{
    use Enumable;

    case STATUS_CHANGED = 'status_changed';
    case DISPUTE_CHANGED = 'dispute_changed';
    case AMOUNT_CHANGED = 'amount_changed';
    case MANUAL_CONTROL_CHANGED = 'manual_control_changed';
    case CALLBACK_SENT = 'callback_sent';
    case PROVIDER_CALLBACK_RECEIVED = 'provider_callback_received';
    case PROVIDER_OPERATION = 'provider_operation';
    case COLLATERAL_CHANGED = 'collateral_changed';
    case TIMEOUT = 'timeout';
    case ERROR = 'error';
}
