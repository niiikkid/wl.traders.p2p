<?php

namespace App\Observers;

use App\Events\NewWalletTransactionCreatedEvent;
use App\Models\Transaction;

class TransactionObserver
{
    public $afterCommit = true;

    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        NewWalletTransactionCreatedEvent::dispatch($transaction); //TODO
    }

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        //
    }

    /**
     * Handle the Transaction "deleted" event.
     */
    public function deleted(Transaction $transaction): void
    {
        //
    }

    /**
     * Handle the Transaction "restored" event.
     */
    public function restored(Transaction $transaction): void
    {
        //
    }

    /**
     * Handle the Transaction "force deleted" event.
     */
    public function forceDeleted(Transaction $transaction): void
    {
        //
    }
}
