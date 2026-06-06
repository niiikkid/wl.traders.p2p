<?php

namespace App\Listeners;

use App\Events\DisputeOpenedEvent;
use App\Jobs\SendTelegramTraderTeamDisputeNotificationJob;

class SendTelegramTraderTeamDisputeNotificationListener
{
    public function handle(DisputeOpenedEvent $event): void
    {
        SendTelegramTraderTeamDisputeNotificationJob::dispatch($event->dispute->id);
    }
}
