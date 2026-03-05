<?php

namespace App\Listeners;

use App\Enums\BalanceType;
use App\Enums\TransactionType;
use App\Events\DetailsAssignedToOrderEvent;
use App\Jobs\ExpiresOrderJob;
use App\Jobs\SendOrderCallbackJob;
use App\Services\Order\Utils\DailyLimit;
use App\Utils\Transaction;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandleDetailsAssignedToOrderListener implements ShouldQueue
{
    public int $tries = 3;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(DetailsAssignedToOrderEvent $event): void
    {
        Transaction::run(function () use ($event) {
            ExpiresOrderJob::dispatch($event->order)->delay($event->order->expires_at);

            SendOrderCallbackJob::dispatch($event->order);

        });
    }

    public function viaQueue(): string
    {
        return 'order';
    }

    public function backoff(): array
    {
        return [5, 10, 15];
    }
}
