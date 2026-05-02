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
use App\Models\Order;
use App\Models\ValueObjects\CascadeManualControl;
use Illuminate\Support\Facades\DB;

class CascadeDealSyncService
{
    public function __construct(
        private readonly CascadeDealEventRecorder $events = new CascadeDealEventRecorder,
    ) {}

    public function syncFromInternalOrder(Order $order): ?CascadeDeal
    {
        $deal = CascadeDeal::query()
            ->with('merchant')
            ->where('order_id', $order->id)
            ->first();

        if (! $deal) {
            return null;
        }

        $beforeCallbackPayload = $this->buildCallbackPayloadSnapshot($deal);

        $deal = DB::transaction(function () use ($deal, $order): CascadeDeal {
            $deal->refresh();
            $fromStatus = $deal->status?->value;
            $fromSubStatus = $deal->sub_status?->value;
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
            }

            return $deal->refresh();
        });

        $afterCallbackPayload = $this->buildCallbackPayloadSnapshot($deal->loadMissing('merchant'));

        if ($beforeCallbackPayload !== $afterCallbackPayload) {
            SendCascadeDealCallbackJob::dispatch($deal);
        }

        return $deal;
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
}
