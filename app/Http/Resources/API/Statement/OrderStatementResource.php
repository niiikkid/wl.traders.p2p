<?php

namespace App\Http\Resources\API\Statement;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderStatementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var Order $this
         */
        return [
            'order_id' => $this->uuid,
            'external_id' => $this->external_id,
            'payin' => [
                'initial_amount' => $this->base_amount->toPrecision(),
                'amount' => $this->amount->toPrecision(),
                'currency' => $this->currency->getCode(),
            ],
            'credit' => [
                'amount' => $this->merchant_profit->toPrecision(),
                'currency' => $this->merchant_profit->getCurrency()->getCode(),
            ],
            'rate' => [
                'amount' => $this->conversion_price?->toPrecision(),
                'market' => $this->market?->value,
                'rate_fixed_at' => $this->rate_fixed_at?->getTimestamp(),
            ],
            'status' => $this->status->value,
            'created_at' => $this->created_at?->getTimestamp(),
        ];
    }
}
