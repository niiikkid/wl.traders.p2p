<?php

namespace App\Observers;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Services\Notification\Events\WithdrawalRequestedNotificationEvent;

class InvoiceObserver
{
    public bool $afterCommit = true;

    public function created(Invoice $invoice): void
    {
        if ($invoice->type->equals(InvoiceType::WITHDRAWAL) && $invoice->status->equals(InvoiceStatus::PENDING)) {
            $invoice->loadMissing('wallet.user');

            services()->notification()->dispatch(new WithdrawalRequestedNotificationEvent($invoice));
        }
    }
}
