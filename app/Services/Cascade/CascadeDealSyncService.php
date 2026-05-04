<?php

declare(strict_types=1);

namespace App\Services\Cascade;

use App\Enums\CascadeDealEventType;
use App\Enums\CascadeDealStatus;
use App\Enums\CascadeDealSubStatus;
use App\Enums\CascadeDisputeStatus;
use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Http\Resources\API\V2\OrderResource;
use App\Jobs\SendCascadeDealCallbackJob;
use App\Models\CascadeDeal;
use App\Models\CascadeProviderLog;
use App\Models\Order;
use App\Models\ValueObjects\CascadeManualControl;
use Illuminate\Support\Facades\DB;

class CascadeDealSyncService
{
    public function __construct(
        private readonly CascadeDealEventRecorder $events,
    ) {}

    public function syncFromInternalOrder(Order $order): ?CascadeDeal
    {
        $deal = CascadeDeal::query()
            ->with(['merchant', 'selectedProvider', 'selectedTransaction'])
            ->where('order_id', $order->id)
            ->first();

        if (! $deal) {
            return null;
        }

        if ($deal->status?->isFinal() === true) {
            $this->recordIgnoredInternalProviderCallback($deal, $order);

            return $deal;
        }

        $beforeCallbackPayload = $this->buildCallbackPayloadSnapshot($deal);

        $callbackRevision = null;

        $deal = DB::transaction(function () use ($deal, $order, $beforeCallbackPayload, &$callbackRevision): CascadeDeal {
            $deal->refresh();
            $fromStatus = $deal->status?->value;
            $fromSubStatus = $deal->sub_status?->value;
            $oldAmount = $deal->amount;
            $manualControl = $deal->manual_control;

            if ($order->manual_control_acquiring) {
                $manualControl = CascadeManualControl::make(
                    manualControlAcquiring: true,
                    cardNumber: $manualControl?->cardNumber,
                    expiryMonth: $manualControl?->expiryMonth,
                    expiryYear: $manualControl?->expiryYear,
                    cvc: $manualControl?->cvc,
                    cardholderName: $manualControl?->cardholderName,
                    confirmationType: $order->manual_control_confirmation_type,
                    rejectReason: $order->manual_control_reject_reason,
                );
            }

            $dispute = $order->dispute;
            $disputeStatus = $dispute ? $this->mapDisputeStatus($dispute->status) : $deal->dispute_status;
            $disputeHistory = $deal->dispute_history ?? [];

            if ($dispute && ($deal->dispute_status?->value !== $disputeStatus?->value || $deal->dispute_reason !== $dispute->reason)) {
                $disputeHistory[] = [
                    'status' => $disputeStatus?->value,
                    'reason' => $dispute->reason,
                    'changed_at' => now()->toDateTimeString(),
                ];
            }

            $deal->update([
                'amount' => $order->amount,
                'debit' => $order->total_profit,
                'credit' => $order->merchant_profit,
                'service_profit' => $order->service_profit,
                'usdt_amount' => $order->total_profit,
                'market' => $order->market,
                'conversion_price' => $order->conversion_price,
                'rate_fixed_at' => $order->rate_fixed_at,
                'status' => $this->mapOrderStatus($order->status),
                'sub_status' => $this->mapOrderSubStatus($order->sub_status),
                'manual_control' => $manualControl,
                'dispute_status' => $disputeStatus,
                'dispute_reason' => $dispute?->reason,
                'dispute_history' => $disputeHistory,
                'dispute_canceled_at' => $dispute?->status?->equals(DisputeStatus::CANCELED) ? $dispute->updated_at : $deal->dispute_canceled_at,
                'finished_at' => $order->finished_at,
            ]);

            if ($oldAmount && ! $oldAmount->equals($order->amount)) {
                $this->events->record(
                    deal: $deal,
                    type: CascadeDealEventType::AMOUNT_CHANGED,
                    payload: [
                        'source' => 'internal_order',
                        'old_amount' => $oldAmount->toBeauty(),
                        'new_amount' => $order->amount->toBeauty(),
                        'currency' => $order->amount->getCurrency()->getCode(),
                    ],
                );
            }

            if ($fromStatus !== $deal->status?->value || $fromSubStatus !== $deal->sub_status?->value) {
                $this->events->record(
                    deal: $deal,
                    type: CascadeDealEventType::STATUS_CHANGED,
                    payload: ['source' => 'internal_order', 'order_id' => $order->uuid],
                    fromStatus: $fromStatus,
                    fromSubStatus: $fromSubStatus,
                    toStatus: $deal->status?->value,
                    toSubStatus: $deal->sub_status?->value,
                );

                if ($deal->selectedProvider) {
                    CascadeProviderLog::create([
                        'cascade_deal_id' => $deal->id,
                        'cascade_transaction_id' => $deal->selected_transaction_id,
                        'provider_id' => $deal->selectedProvider->id,
                        'operation' => 'callback',
                        'method' => 'POST',
                        'url' => 'internal://cascade.syncFromInternalOrder',
                        'request_payload' => $this->buildInternalProviderCallbackPayload($deal, $order, $fromStatus, $fromSubStatus),
                        'response_payload' => [],
                        'status_code' => 200,
                        'is_successful' => true,
                    ]);
                }
            }

            $deal->refresh();

            $afterCallbackPayload = $this->buildCallbackPayloadSnapshot($deal->loadMissing('merchant'));

            if ($beforeCallbackPayload !== $afterCallbackPayload) {
                $callbackRevision = $deal->callback_payload_revision + 1;
                $deal->forceFill(['callback_payload_revision' => $callbackRevision])->save();
                $deal->refresh();
            }

            return $deal;
        });

        if ($callbackRevision !== null) {
            SendCascadeDealCallbackJob::dispatch($deal, $callbackRevision);
        }

        return $deal;
    }

    private function recordIgnoredInternalProviderCallback(CascadeDeal $deal, Order $order): void
    {
        if (! $deal->selectedProvider) {
            return;
        }

        CascadeProviderLog::create([
            'cascade_deal_id' => $deal->id,
            'cascade_transaction_id' => $deal->selected_transaction_id,
            'provider_id' => $deal->selectedProvider->id,
            'operation' => 'callback',
            'method' => 'POST',
            'url' => 'internal://cascade.syncFromInternalOrder',
            'request_payload' => $this->buildInternalProviderCallbackPayload(
                $deal,
                $order,
                $deal->status?->value,
                $deal->sub_status?->value,
            ),
            'response_payload' => [
                'ignored' => true,
                'reason' => 'cascade_deal_final_status',
                'cascade_deal_status' => $deal->status?->value,
                'cascade_deal_sub_status' => $deal->sub_status?->value,
            ],
            'status_code' => 200,
            'is_successful' => true,
        ]);
    }

    private function mapOrderStatus(?OrderStatus $status): CascadeDealStatus
    {
        return match ($status?->value) {
            OrderStatus::SUCCESS->value => CascadeDealStatus::SUCCESS,
            OrderStatus::FAIL->value => CascadeDealStatus::FAIL,
            default => CascadeDealStatus::PENDING,
        };
    }

    private function mapOrderSubStatus(?OrderSubStatus $subStatus): CascadeDealSubStatus
    {
        return match ($subStatus?->value) {
            OrderSubStatus::SUCCESSFULLY_PAID->value,
            OrderSubStatus::ACCEPTED->value => CascadeDealSubStatus::SUCCESSFULLY_PAID,
            OrderSubStatus::SUCCESSFULLY_PAID_BY_RESOLVED_DISPUTE->value => CascadeDealSubStatus::SUCCESSFULLY_PAID_BY_RESOLVED_DISPUTE,
            OrderSubStatus::WAITING_FOR_DISPUTE_TO_BE_RESOLVED->value => CascadeDealSubStatus::WAITING_FOR_DISPUTE_TO_BE_RESOLVED,
            OrderSubStatus::CANCELED_BY_DISPUTE->value => CascadeDealSubStatus::CANCELED_BY_DISPUTE,
            OrderSubStatus::CANCELED->value,
            OrderSubStatus::EXPIRED->value => CascadeDealSubStatus::CANCELED,
            default => CascadeDealSubStatus::WAITING_FOR_PAYMENT,
        };
    }

    private function mapDisputeStatus(?DisputeStatus $status): ?CascadeDisputeStatus
    {
        return match ($status?->value) {
            DisputeStatus::PENDING->value => CascadeDisputeStatus::OPENED,
            DisputeStatus::ACCEPTED->value => CascadeDisputeStatus::ACCEPTED,
            DisputeStatus::CANCELED->value => CascadeDisputeStatus::REJECTED,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCallbackPayloadSnapshot(CascadeDeal $deal): array
    {
        $payload = OrderResource::make($deal)->resolve();

        unset($payload['current_server_time']);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInternalProviderCallbackPayload(
        CascadeDeal $deal,
        Order $order,
        ?string $fromStatus,
        ?string $fromSubStatus,
    ): array {
        return [
            'source' => 'internal_order',
            'cascade_deal_uuid' => $deal->uuid,
            'provider_deal_id' => $order->uuid,
            'status' => $deal->status?->value,
            'sub_status' => $deal->sub_status?->value,
            'from_status' => $fromStatus,
            'from_sub_status' => $fromSubStatus,
            'amount' => $order->amount?->toPrecision(),
            'currency' => $order->amount?->getCurrency()->getCode(),
            'finished_at' => $order->finished_at?->getTimestamp(),
            'raw' => [
                'order_id' => $order->uuid,
                'status' => $order->status?->value,
                'sub_status' => $order->sub_status?->value,
            ],
        ];
    }
}
