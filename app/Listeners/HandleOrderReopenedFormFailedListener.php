<?php

namespace App\Listeners;

use App\Enums\TransactionType;
use App\Events\OrderReopenedFromFailedEvent;
use App\Services\Order\OrderTraderDebitService;
use App\Services\Order\Utils\DailyLimit;
use App\Services\Order\Utils\DailySuccessfulOrdersLimit;
use App\Services\Order\Utils\MonthlyLimit;
use App\Services\Order\Utils\MonthlySuccessfulOrdersLimit;
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
            MonthlyLimit::increment($event->order->payment_detail_id, $event->order->amount, $event->order->created_at);
            DailySuccessfulOrdersLimit::increment($event->order->payment_detail_id, $event->order->created_at);
            MonthlySuccessfulOrdersLimit::increment($event->order->payment_detail_id, $event->order->created_at);

            $event->order->loadMissing(['trader.wallet', 'teamLeader.wallet']);

            $allocation = app(OrderTraderDebitService::class)->debit(
                $event->order->trader,
                $event->order->trader_paid_for_order,
                $event->order,
                TransactionType::PAYMENT_FOR_OPENED_ORDER,
            );

            $event->order->update($allocation?->toOrderAttributes() ?? [
                'trader_trust_paid_for_order' => null,
                'team_leader_reserve_paid_for_order' => null,
            ]);
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
