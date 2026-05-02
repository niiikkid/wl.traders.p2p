<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Events\OrderSucceeded;
use App\Jobs\SendOrderCallbackJob;
use App\Models\CascadeDeal;
use App\Models\Order;
use App\Services\Cascade\CascadeDealSyncService;

class OrderObserver
{
    /**
     * @var array<int, string>
     */
    private const CASCADE_SYNC_RELEVANT_FIELDS = [
        'amount',
        'total_profit',
        'merchant_profit',
        'service_profit',
        'market',
        'conversion_price',
        'rate_fixed_at',
        'status',
        'sub_status',
        'manual_control_acquiring',
        'manual_control_confirmation_type',
        'manual_control_reject_reason',
        'finished_at',
    ];

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

        $cascadeDealExists = CascadeDeal::query()
            ->where('order_id', $order->id)
            ->exists();

        if ($cascadeDealExists) {
            if ($this->hasCascadeSyncRelevantChanges($order)) {
                app(CascadeDealSyncService::class)->syncFromInternalOrder($order);
            }

            return;
        }

        if ($order->wasChanged('status') || $order->isDirty('status')) {
            SendOrderCallbackJob::dispatch($order);
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

    private function hasCascadeSyncRelevantChanges(Order $order): bool
    {
        $changes = array_keys($order->getChanges());

        return (bool) array_intersect($changes, self::CASCADE_SYNC_RELEVANT_FIELDS);
    }
}
