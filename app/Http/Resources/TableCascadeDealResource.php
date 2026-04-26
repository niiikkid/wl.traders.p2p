<?php

namespace App\Http\Resources;

use App\Models\CascadeDeal;
use App\Services\Money\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'order_uuid' => $this->order?->uuid,
            'amount' => $this->amount?->toBeauty(),
            'initial_amount' => $this->initial_amount?->toBeauty(),
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
            'status_name' => $this->status ? trans("order.status.{$this->status->value}") : null,
            'sub_status' => $this->sub_status?->value,
            'sub_status_name' => $this->sub_status
                ? trans("order.sub_status.{$this->sub_status->value}")
                : null,
            'payment_method' => $this->payment_method?->value,
            'payment_method_name' => $this->payment_method
                ? trans("cascade.payment_method.{$this->payment_method->value}")
                : null,
            'callback_url' => $this->callback_url,
            'selected_provider' => $this->selectedProvider ? [
                'id' => $this->selectedProvider->id,
                'code' => $this->selectedProvider->code,
                'name' => $this->selectedProvider->name,
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
            'provider_logs_count' => $this->provider_logs_count,
            'finished_at' => $this->finished_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
