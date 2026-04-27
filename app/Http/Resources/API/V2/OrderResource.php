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
            'payin_id' => $this->uuid,
            'external_id' => $this->external_id,
            'merchant_id' => $merchant->uuid,
            'status' => $this->status->value,
            'sub_status' => $this->sub_status->value,
            'amounts' => [
                'amount' => $this->amount?->toBeauty(),
                'initial_amount' => $this->initial_amount?->toBeauty(),
                'currency' => $this->currency?->getCode(),
            ],
            'converted_amount' => [
                'amount' => $this->usdt_amount?->toBeauty(),
                'currency' => 'USDT',
            ],
            'profit' => [
                'amount' => $this->credit?->toBeauty(),
                'currency' => $this->credit?->getCurrency()->getCode(),
            ],
            'conversion_price' => [
                'amount' => $this->conversion_price?->toBeauty(),
                'currency' => $this->conversion_price?->getCurrency()->getCode(),
                'market' => $this->market?->value,
                'rate_fixed_at' => $this->rate_fixed_at?->getTimestamp(),
            ],
            'payment_method' => $this->payment_method?->value,
            'gateway' => [
                'name' => $this->gateway['name'],
            ],
            'details' => $this->manual_control === null ? null : [
                'initials' => $this->details?->initials ?? null,
                'value' => $this->details?->value,
            ],
            //'manual_control_acquiring' => $this->manual_control === null ? null : [
            //    'confirmation_type' => ?,
            //    'reject_reason' => ?,
            //],
            //'dispute' => [
            //    'status' => ?,
            //    'reason' => ?,
            //    'canceled_at' => ?,
            //],
            'callback_url' => $this->callback_url,
            'finished_at' => $this->finished_at?->getTimestamp(),
            'created_at' => $this->created_at->getTimestamp(),
            'current_server_time' => now()->getTimestamp(),
        ];
    }
}