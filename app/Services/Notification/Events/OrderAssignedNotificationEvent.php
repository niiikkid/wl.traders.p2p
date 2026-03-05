<?php

namespace App\Services\Notification\Events;

use App\Enums\NotificationEvent;
use App\Models\Order;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Support\Collection;

class OrderAssignedNotificationEvent implements NotificationEventInterface
{
    public function __construct(
        protected Order $order
    ) {}

    public function type(): NotificationEvent
    {
        return NotificationEvent::ORDER_ASSIGNED;
    }

    public function recipients(): Collection
    {
        return collect([$this->order->trader])->filter();
    }

    public function currency(): ?Currency
    {
        return $this->order->currency;
    }

    public function amount(): ?Money
    {
        return $this->order->amount;
    }

    public function status(): ?string
    {
        return null;
    }

    public function payload(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_uuid' => $this->order->uuid,
            'amount' => $this->order->amount->toBeauty(),
            'currency' => $this->order->currency->getCode(),
            'merchant_id' => $this->order->merchant_id,
        ];
    }
}
