<?php

namespace App\Http\Resources\API\V2;

use App\Models\CascadeDeal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /**
         * @var CascadeDeal $this
         */
        $merchant = queries()->merchant()->findByID($this->merchant_id);

        return [
            'cascade_deal_id' => $this->uuid,
            'order_id' => $this->uuid,
            'external_id' => $this->external_id,
            'merchant_id' => $merchant->uuid,
            'status' => $this->status->value,
            'sub_status' => $this->sub_status->value,
            'amounts' => [
                'amount' => $this->amount?->toBeauty(),
                'initial_amount' => $this->initial_amount?->toBeauty(),
                'currency' => $this->currency?->getCode(),
            ],
            'profit' => [
                'amount' => $this->service_profit?->toBeauty() ?? 0,
                'currency' => $this->service_profit?->getCurrency()->getCode() ?? 'USDT',
            ],
            'payment_method' => $this->payment_method?->value,
            'gateway' => $this->gateway,
            'details' => $this->details,
            'callback_url' => $this->callback_url,
            'finished_at' => $this->finished_at?->getTimestamp(),
            'created_at' => $this->created_at->getTimestamp(),
            'current_server_time' => now()->getTimestamp(),
        ];
    }
}
