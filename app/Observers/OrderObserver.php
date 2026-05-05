<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Events\OrderSucceeded;
use App\Jobs\CascadeInternalProviderCallbackJob;
use App\Jobs\SendOrderCallbackJob;
use App\Models\CascadeDeal;
use App\Models\Order;

class OrderObserver
{
    public $afterCommit = true;

    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void {}

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        if ($order->wasChanged('status') && $order->status->equals(OrderStatus::SUCCESS)) {
            event(new OrderSucceeded($order));
        }

        if ($order->wasChanged('status') || $order->isDirty('status')) {

            $cascadeDealExists = CascadeDeal::query()
            ->where('order_id', $order->id)
            ->exists();

            if ($cascadeDealExists) {
                CascadeInternalProviderCallbackJob::dispatch($order->id);
            } else {
                SendOrderCallbackJob::dispatch($order);
            }
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        //
    }
}
