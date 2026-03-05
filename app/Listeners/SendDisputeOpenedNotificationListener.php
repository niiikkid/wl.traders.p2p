<?php

namespace App\Listeners;

use App\Events\DisputeOpenedEvent;
use App\Services\Notification\Events\DisputeOpenedNotificationEvent;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendDisputeOpenedNotificationListener implements ShouldQueue
{
    public int $tries = 3;

    public function handle(DisputeOpenedEvent $event): void
    {
        $event->dispute->loadMissing('order', 'trader');

        services()->notification()->dispatch(new DisputeOpenedNotificationEvent($event->dispute));
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
