<?php

namespace App\Services\Notification\Events;

use App\Enums\NotificationEvent;
use App\Models\User;
use App\Models\WalletDepositInvoice;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Support\Collection;

class WalletDepositPaidNotificationEvent implements NotificationEventInterface
{
    public function __construct(
        protected WalletDepositInvoice $invoice
    ) {}

    public function type(): NotificationEvent
    {
        return NotificationEvent::WALLET_DEPOSIT_PAID;
    }

    public function recipients(): Collection
    {
        $this->invoice->loadMissing('wallet.user');

        $user = $this->invoice->wallet?->user;

        return $user instanceof User ? collect([$user]) : collect();
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
        return $this->invoice->status->value;
    }

    public function payload(): array
    {
        return [
            'invoice_uuid' => $this->invoice->uuid,
            'amount' => $this->invoice->amount->toBeauty(),
            'currency' => $this->invoice->currency->getCode(),
            'txid' => $this->invoice->txid,
        ];
    }
}
