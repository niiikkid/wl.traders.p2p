<?php

namespace App\Http\Resources;

use App\Models\CascadeDeal;
use App\Models\CascadeProviderLog;
use App\Services\Money\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class TableCascadeDealResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var CascadeDeal $this
         */
        $cascadeDisputeReceiptBatches = collect($this->dispute_receipts ?? []);
        $cascadeDisputeReceiptIndex = 0;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'external_id' => $this->external_id,
            'merchant' => [
                'id' => $this->merchant?->id,
                'uuid' => $this->merchant?->uuid,
                'name' => $this->merchant?->name,
            ],
            'merchant_client' => [
                'id' => $this->merchantClient?->id,
                'external_id' => $this->merchantClient?->external_id,
            ],
            'order_id' => $this->order_id,
            'order_uuid' => $this->order?->uuid,
            'amount' => $this->amount?->toBeauty(),
            'initial_amount' => $this->initial_amount?->toBeauty(),
            'amount_was_modified' => $this->amountWasModified(),
            'currency' => $this->currency?->getCode(),
            'debit' => $this->debit?->toBeauty(),
            'credit' => $this->credit?->toBeauty(),
            'service_profit' => $this->service_profit?->toBeauty(),
            'usdt_amount' => $this->usdt_amount?->toBeauty(),
            'fee' => $this->fee?->toBeauty(),
            'fee_rate' => $this->fee_rate,
            'base_currency' => Currency::USDT()->getCode(),
            'market' => $this->market?->value,
            'conversion_price' => $this->conversion_price?->toBeauty(),
            'rate_fixed_at' => $this->rate_fixed_at?->toISOString(),
            'status' => $this->status?->value,
            'status_name' => $this->status ? trans("cascade.status.{$this->status->value}") : null,
            'sub_status' => $this->sub_status?->value,
            'sub_status_name' => $this->sub_status
                ? trans("cascade.sub_status.{$this->sub_status->value}")
                : null,
            'dispute' => [
                'status' => $this->dispute_status?->value,
                'status_name' => $this->dispute_status
                    ? trans("cascade.dispute_status.{$this->dispute_status->value}")
                    : null,
                'reason' => $this->dispute_reason,
                'receipts' => $cascadeDisputeReceiptBatches
                    ->map(function (array $batch) use (&$cascadeDisputeReceiptIndex): array {
                        $files = collect($batch['files'] ?? [])
                            ->map(function (array $file) use (&$cascadeDisputeReceiptIndex): array {
                                $index = $cascadeDisputeReceiptIndex++;

                                return [
                                    ...$file,
                                    'url' => ! empty($file['stored_name'])
                                        ? route('admin.cascade-deals.dispute.receipts.show', [
                                            'cascadeDeal' => $this->id,
                                            'receipt' => $index,
                                        ])
                                        : null,
                                ];
                            })
                            ->all();

                        return [
                            ...$batch,
                            'files' => $files,
                        ];
                    })
                    ->all(),
                'history' => $this->dispute_history,
                'canceled_at' => $this->dispute_canceled_at?->toISOString(),
            ],
            'can_view_cascade_dispute' => $this->dispute_status !== null
                || (($this->dispute_history ?? []) !== [])
                || (($this->dispute_receipts ?? []) !== []),
            'can_open_dispute' => $this->dispute_status === null
                && ! $this->order?->dispute
                && (($this->dispute_history ?? []) === [])
                && (($this->dispute_receipts ?? []) === [])
                && $this->selected_transaction_id !== null,
            'payment_method' => $this->payment_method?->value,
            'payment_method_name' => $this->payment_method
                ? trans("cascade.payment_method.{$this->payment_method->value}")
                : null,
            'callback_url' => $this->callback_url,
            'selected_provider' => $this->selectedProvider ? [
                'id' => $this->selectedProvider->id,
                'code' => $this->selectedProvider->code,
                'name' => $this->selectedProvider->name,
                'provider_type' => $this->selectedProvider->provider_type?->value,
            ] : null,
            'selected_transaction' => $this->selectedTransaction ? [
                'id' => $this->selectedTransaction->id,
                'status' => $this->selectedTransaction->status?->value,
                'status_name' => $this->selectedTransaction->status
                    ? trans("cascade.transaction_status.{$this->selectedTransaction->status->value}")
                    : null,
                'provider_deal_id' => $this->selectedTransaction->provider_deal_id,
                'error_code' => $this->selectedTransaction->error_code,
                'error_message' => $this->selectedTransaction->error_message,
            ] : null,
            'transactions_count' => $this->transactions_count,
            'transactions' => $this->whenLoaded('transactions', fn () => $this->transactions->map(fn ($transaction) => [
                'id' => $transaction->id,
                'status' => $transaction->status?->value,
                'status_name' => $transaction->status
                    ? trans("cascade.transaction_status.{$transaction->status->value}")
                    : null,
                'provider_deal_id' => $transaction->provider_deal_id,
                'usdt_amount' => $transaction->usdt_amount?->toBeauty(),
                'fee' => $transaction->fee?->toBeauty(),
                'fee_rate' => $transaction->fee_rate,
                'credit' => $transaction->credit?->toBeauty(),
                'request_payload' => $transaction->request_payload,
                'response_payload' => $transaction->response_payload,
                'error_code' => $transaction->error_code,
                'error_message' => $transaction->error_message,
                'provider' => $transaction->provider ? [
                    'id' => $transaction->provider->id,
                    'code' => $transaction->provider->code,
                    'name' => $transaction->provider->name,
                    'provider_type' => $transaction->provider->provider_type?->value,
                ] : null,
                'created_at' => $transaction->created_at?->toISOString(),
                'updated_at' => $transaction->updated_at?->toISOString(),
            ])),
            'provider_logs_count' => $this->provider_logs_count,
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($event) => [
                'id' => $event->id,
                'type' => $event->type?->value,
                'provider' => $event->provider ? [
                    'id' => $event->provider->id,
                    'code' => $event->provider->code,
                    'name' => $event->provider->name,
                ] : null,
                'cascade_transaction' => $event->cascadeTransaction ? [
                    'id' => $event->cascadeTransaction->id,
                    'status' => $event->cascadeTransaction->status?->value,
                    'provider_deal_id' => $event->cascadeTransaction->provider_deal_id,
                ] : null,
                'from_status' => $event->from_status,
                'from_sub_status' => $event->from_sub_status,
                'to_status' => $event->to_status,
                'to_sub_status' => $event->to_sub_status,
                'payload' => $event->payload,
                'created_at' => $event->created_at?->toISOString(),
            ])),
            'amount_history' => $this->whenLoaded('amountChangeEvents', fn () => $this->amountChangeEvents->map(fn ($event) => [
                'id' => $event->id,
                'source' => Arr::get($event->payload, 'source'),
                'old_amount' => Arr::get($event->payload, 'old_amount'),
                'new_amount' => Arr::get($event->payload, 'new_amount'),
                'currency' => Arr::get($event->payload, 'currency', $this->currency?->getCode()),
                'created_at' => $event->created_at?->toISOString(),
            ])),
            'provider_logs' => $this->whenLoaded('providerLogs', fn () => $this->providerLogs->map(fn ($log) => [
                'id' => $log->id,
                'type' => $log->operation === 'callback' ? 'callback' : 'api',
                'operation' => $log->operation,
                'operation_label' => CascadeProviderLog::operationLabel($log->operation),
                'method' => $log->method,
                'url' => $log->url,
                'status_code' => $log->status_code,
                'execution_time' => $log->execution_time,
                'is_successful' => $log->is_successful,
                'error_code' => $log->error_code,
                'error_message' => $log->error_message,
                'request_payload' => $log->request_payload,
                'response_payload' => $log->response_payload,
                'provider' => $log->provider ? [
                    'id' => $log->provider->id,
                    'code' => $log->provider->code,
                    'name' => $log->provider->name,
                ] : null,
                'cascade_transaction' => $log->cascadeTransaction ? [
                    'id' => $log->cascadeTransaction->id,
                    'status' => $log->cascadeTransaction->status?->value,
                    'provider_deal_id' => $log->cascadeTransaction->provider_deal_id,
                ] : null,
                'created_at' => $log->created_at?->toISOString(),
            ])),
            'collateral_holds' => $this->whenLoaded('collateralHolds', fn () => $this->collateralHolds->map(fn ($hold) => [
                'id' => $hold->id,
                'amount' => $hold->amount?->toBeauty(),
                'currency' => $hold->currency?->getCode(),
                'status' => $hold->status?->value,
                'created_at' => $hold->created_at?->toISOString(),
            ])),
            'finished_at' => $this->finished_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
