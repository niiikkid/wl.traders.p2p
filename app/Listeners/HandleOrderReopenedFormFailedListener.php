<?php

namespace App\Listeners;

use App\Enums\BalanceType;
use App\Enums\TransactionType;
use App\Events\OrderReopenedFromFailedEvent;
use App\Services\Order\Utils\DailyLimit;
use App\Services\Order\Utils\DailySuccessfulOrdersLimit;
use App\Utils\Transaction;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandleOrderReopenedFormFailedListener implements ShouldQueue
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
    public function handle(OrderReopenedFromFailedEvent $event): void
    {
        Transaction::run(function () use ($event) {
            DailyLimit::increment($event->order->payment_detail_id, $event->order->amount, $event->order->created_at);
            DailySuccessfulOrdersLimit::increment($event->order->payment_detail_id, $event->order->created_at);

            services()->wallet()->takeFromBalance(
                $event->order->paymentDetail->user->wallet->id,
                $event->order->trader_paid_for_order,
                TransactionType::PAYMENT_FOR_OPENED_ORDER,
                BalanceType::TRUST
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
