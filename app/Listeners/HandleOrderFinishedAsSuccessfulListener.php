<?php

namespace App\Listeners;

use App\Enums\BalanceType;
use App\Enums\TransactionType;
use App\Events\OrderFinishedAsSuccessfulEvent;
use App\Exceptions\OrderException;
use App\Utils\Transaction;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandleOrderFinishedAsSuccessfulListener implements ShouldQueue
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
    public function handle(OrderFinishedAsSuccessfulEvent $event): void
    {
        Transaction::run(function () use ($event) {
            $event->order->merchant->loadMissing('wallet');
            if (! $event->order->merchant->wallet) {
                throw OrderException::merchantWalletMissing();
            }

            services()->wallet()->giveToBalance(
                $event->order->merchant->wallet->id,
                $event->order->merchant_profit,
                TransactionType::INCOME_FROM_A_SUCCESSFUL_ORDER,
                BalanceType::MERCHANT,
                $event->order,
            );
            if ($event->order->team_leader_id) {
                services()->wallet()->giveToBalance(
                    $event->order->teamLeader->wallet->id,
                    $event->order->team_leader_profit,
                    TransactionType::INCOME_FROM_REFERRALS_SUCCESSFUL_ORDER,
                    BalanceType::TEAMLEADER,
                    $event->order,
                );
            }
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
