<?php

namespace App\Services\Notification\Events;

use App\Enums\NotificationEvent;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Support\Collection;

interface NotificationEventInterface
{
    public function type(): NotificationEvent;

    /**
     * @return Collection<int, \App\Models\User>
     */
    public function recipients(): Collection;

    public function currency(): ?Currency;

    public function amount(): ?Money;

    public function status(): ?string;

    /**
     * @return array<string, mixed>
     */
    public function payload(): array;
}
