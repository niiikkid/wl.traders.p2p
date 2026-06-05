<?php

namespace App\Services\Notification\Events;

use App\Enums\NotificationEvent;
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Support\Collection;

class PayoutsAvailableNotificationEvent implements NotificationEventInterface
{
    public function __construct(
        protected User $trader,
        protected Currency $currency,
        protected int $availableCount
    ) {}

    public function type(): NotificationEvent
    {
        return NotificationEvent::PAYOUTS_AVAILABLE;
    }

    public function recipients(): Collection
    {
        return collect([$this->trader]);
    }

    public function currency(): ?Currency
    {
        return $this->currency;
    }

    public function amount(): ?Money
    {
        return null;
    }

    public function status(): ?string
    {
        return null;
    }

    public function payload(): array
    {
        return [
            'user_id' => $this->trader->id,
            'currency' => $this->currency->getCode(),
            'available_count' => $this->availableCount,
        ];
    }
}
