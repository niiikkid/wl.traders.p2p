<?php

namespace App\Http\Resources\API\Statement;

use App\Models\Payout\Payout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutStatementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var Payout $this
         */
        return [
            'uuid' => $this->uuid,
            'external_id' => $this->external_id,
            'payout' => [
                'amount' => $this->amount_fiat->toPrecision(),
                'currency' => $this->amount_fiat->getCurrency()->getCode(),
            ],
            'debit' => [
                'amount' => $this->merchant_debit?->toPrecision(),
                'currency' => $this->merchant_debit->getCurrency()->getCode(),
            ],
            'rate' => [
                'value' => $this->conversion_price->toPrecision(),
                'market' => $this->rate_market->value,
                'rate_fixed_at' => $this->rate_fixed_at?->getTimestamp(),
            ],
            'status' => $this->status->value,
            'created_at' => $this->created_at?->getTimestamp(),
        ];
    }
}
