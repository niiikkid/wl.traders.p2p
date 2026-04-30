<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CascadeDeal;
use App\Services\Money\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CascadeDeal
 */
class MerchantCascadePaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'external_id' => $this->external_id,
            'amount' => $this->amount?->toBeauty(),
            'total_profit' => $this->usdt_amount?->toBeauty(),
            'merchant_profit' => $this->credit?->toBeauty(),
            'service_commission_amount_total' => $this->fee?->toBeauty(),
            'conversion_price' => $this->conversion_price?->toBeauty(),
            'currency' => $this->currency?->getCode() ?? '',
            'base_currency' => Currency::USDT()->getCode(),
            'status' => $this->status?->value,
            'status_name' => $this->status ? trans("cascade.status.{$this->status->value}") : null,
            'callback_url' => $this->callback_url,
            'merchant' => [
                'id' => $this->merchant?->id,
                'name' => $this->merchant?->name,
            ],
            'created_at' => $this->created_at->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'can_open_payment_page' => false,
        ];
    }
}
