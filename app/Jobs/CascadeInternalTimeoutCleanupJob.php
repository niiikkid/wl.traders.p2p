<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CascadeDealEventType;
use App\Enums\CascadeTransactionStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Models\CascadeProvider;
use App\Models\CascadeTransaction;
use App\Models\Order;
use App\Services\Cascade\CascadeDealEventRecorder;
use App\Services\Cascade\Providers\InternalCascadeProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;

class CascadeInternalTimeoutCleanupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 10;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('cascade-internal-cleanup');
    }

    public function handle(CascadeDealEventRecorder $events): void
    {
        $internalProviderId = CascadeProvider::query()
            ->where('code', InternalCascadeProvider::CODE)
            ->value('id');

        if (! $internalProviderId) {
            return;
        }

        CascadeTransaction::query()
            ->with(['cascadeDeal', 'provider'])
            ->where('provider_id', $internalProviderId)
            ->where('status', CascadeTransactionStatus::FAILED_TO_OPEN)
            ->whereNull('provider_deal_id')
            ->where(function ($query) {
                $query->where('error_code', 'timeout')
                    ->orWhere('error_message', 'like', '%timeout%')
                    ->orWhere('error_message', 'like', '%вовремя%');
            })
            ->latest('id')
            ->limit(50)
            ->get()
            ->each(function (CascadeTransaction $transaction) use ($events): void {
                $deal = $transaction->cascadeDeal;

                if (! $deal || $deal->selected_transaction_id === $transaction->id || $deal->order_id !== null) {
                    return;
                }

                $externalId = Arr::get($transaction->request_payload ?? [], 'external_id', $deal->external_id);

                $order = Order::withoutGlobalScopes()
                    ->where('merchant_id', $deal->merchant_id)
                    ->where('external_id', $externalId)
                    ->where('status', OrderStatus::PENDING)
                    ->latest('id')
                    ->first();

                if (! $order) {
                    return;
                }

                services()->order()->finishOrderAsFailed($order->id, OrderSubStatus::CANCELED);

                $transaction->update([
                    'provider_deal_id' => $order->uuid,
                    'response_payload' => array_merge($transaction->response_payload ?? [], [
                        'late_internal_order_cancelled' => true,
                        'provider_deal_id' => $order->uuid,
                    ]),
                ]);

                $events->record(
                    deal: $deal,
                    type: CascadeDealEventType::TIMEOUT,
                    payload: [
                        'action' => 'late_internal_order_cancelled',
                        'order_id' => $order->uuid,
                        'transaction_id' => $transaction->id,
                    ],
                    transaction: $transaction,
                    provider: $transaction->provider,
                );
            });
    }
}
