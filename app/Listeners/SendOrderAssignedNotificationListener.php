<?php

namespace App\Listeners;

use App\Events\DetailsAssignedToOrderEvent;
use App\Services\Notification\Events\OrderAssignedNotificationEvent;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOrderAssignedNotificationListener implements ShouldQueue
{
    public int $tries = 3;

    public function handle(DetailsAssignedToOrderEvent $event): void
    {
        $event->order->loadMissing('trader');

        services()->notification()->dispatch(new OrderAssignedNotificationEvent($event->order));
    }

    public function viaQueue(): string
    {
        return 'notifications';
    }

    public function backoff(): array
    {
        return [5, 10, 15];
    }
}
