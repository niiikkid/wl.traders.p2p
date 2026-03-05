<?php

namespace App\Services\Notification\Events;

use App\Enums\NotificationEvent;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Support\Collection;

class WithdrawalRequestedNotificationEvent implements NotificationEventInterface
{
    public function __construct(
        protected Invoice $invoice
    ) {}

    public function type(): NotificationEvent
    {
        return NotificationEvent::WITHDRAWAL_REQUESTED;
    }

    public function recipients(): Collection
    {
        return User::role('Super Admin')->get();
    }

    public function currency(): ?Currency
    {
        return $this->invoice->currency;
    }

    public function amount(): ?Money
    {
        return $this->invoice->amount;
    }

    public function status(): ?string
    {
        return null;
    }

    public function payload(): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'amount' => $this->invoice->amount->toBeauty(),
            'currency' => $this->invoice->currency->getCode(),
            'user_id' => $this->invoice->wallet?->user_id,
            'user_email' => $this->invoice->wallet?->user?->email,
            'balance_type' => $this->invoice->balance_type?->value,
            'address' => $this->invoice->address,
            'network' => $this->invoice->network?->value,
        ];
    }
}
