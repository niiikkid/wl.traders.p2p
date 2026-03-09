<?php

namespace App\Services\Notification\Events;

use App\Enums\NotificationEvent;
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Support\Collection;

class TrustBalanceLowNotificationEvent implements NotificationEventInterface
{
    public function __construct(
        protected User $trader,
        protected Money $previousBalance,
        protected Money $currentBalance
    ) {}

    public function type(): NotificationEvent
    {
        return NotificationEvent::TRUST_BALANCE_LOW;
    }

    public function recipients(): Collection
    {
        return collect([$this->trader]);
    }

    public function currency(): ?Currency
    {
        return $this->currentBalance->getCurrency();
    }

    public function amount(): ?Money
    {
        return $this->currentBalance;
    }

    public function status(): ?string
    {
        return null;
    }

    public function payload(): array
    {
        return [
            'user_id' => $this->trader->id,
            'previous_balance' => $this->previousBalance->toBeauty(),
            'current_balance' => $this->currentBalance->toBeauty(),
            'currency' => $this->currentBalance->getCurrency()->getCode(),
        ];
    }

    public function crossedBelow(Money $threshold): bool
    {
        return $this->previousBalance->greaterOrEquals($threshold)
            && $this->currentBalance->lessThan($threshold);
    }
}
