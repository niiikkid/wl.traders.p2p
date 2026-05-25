<?php

namespace App\Listeners;

use App\Enums\TransactionType;
use App\Events\OrderFinishedAsFailedEvent;
use App\Services\Order\OrderTraderDebitService;
use App\Services\Order\Utils\DailyLimit;
use App\Services\Order\Utils\DailySuccessfulOrdersLimit;
use App\Services\Order\Utils\MonthlyLimit;
use App\Services\Order\Utils\MonthlySuccessfulOrdersLimit;
use App\Utils\Transaction;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandleOrderFinishedAsFailedListener implements ShouldQueue
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
    public function handle(OrderFinishedAsFailedEvent $event): void
    {
        Transaction::run(function () use ($event) {
            DailyLimit::decrement($event->order->payment_detail_id, $event->order->amount, $event->order->created_at);
            MonthlyLimit::decrement($event->order->payment_detail_id, $event->order->amount, $event->order->created_at);
            DailySuccessfulOrdersLimit::decrement($event->order->payment_detail_id, $event->order->created_at);
            MonthlySuccessfulOrdersLimit::decrement($event->order->payment_detail_id, $event->order->created_at);

            app(OrderTraderDebitService::class)->refund(
                $event->order,
                TransactionType::REFUND_FOR_CANCELED_ORDER,
            );
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
