<?php

namespace App\Enums;

use App\Traits\Enumable;

enum WalletDepositInvoiceStatus: string
{
    use Enumable;

    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PAID = 'paid';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    case AMOUNT_MISMATCH = 'amount_mismatch';
    case FAILED = 'failed';

    /**
     * Terminal statuses that background jobs must never move backward.
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::PAID,
            self::EXPIRED,
            self::CANCELLED,
            self::AMOUNT_MISMATCH,
            self::FAILED => true,
            default => false,
        };
    }

    /**
     * Statuses that the poller keeps scanning on the blockchain.
     */
    public function isOpenForPolling(): bool
    {
        return match ($this) {
            self::PENDING,
            self::PROCESSING => true,
            default => false,
        };
    }
}
