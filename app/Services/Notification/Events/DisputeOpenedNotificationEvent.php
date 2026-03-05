<?php

namespace App\Services\Notification\Events;

use App\Enums\NotificationEvent;
use App\Models\Dispute;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Support\Collection;

class DisputeOpenedNotificationEvent implements NotificationEventInterface
{
    public function __construct(
        protected Dispute $dispute
    ) {}

    public function type(): NotificationEvent
    {
        return NotificationEvent::DISPUTE_OPENED;
    }

    public function recipients(): Collection
    {
        return collect([$this->dispute->trader])->filter();
    }

    public function currency(): ?Currency
    {
        return $this->dispute->order?->currency;
    }

    public function amount(): ?Money
    {
        return $this->dispute->order?->amount;
    }

    public function status(): ?string
    {
        return null;
    }

    public function payload(): array
    {
        return [
            'dispute_id' => $this->dispute->id,
            'order_id' => $this->dispute->order_id,
            'order_uuid' => $this->dispute->order?->uuid,
        ];
    }
}
