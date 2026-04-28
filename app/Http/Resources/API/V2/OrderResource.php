<?php

namespace App\Http\Resources\API\V2;

use App\Models\CascadeDeal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /**
         * @var CascadeDeal $this
         */
        $merchant = $this->merchant ?? queries()->merchant()->findByID($this->merchant_id);
        $gateway = $this->gateway ?? [];
        $details = $this->details ?? [];

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
                'name' => Arr::get($gateway, 'name'),
            ],
            'details' => empty($details) ? null : [
                'initials' => Arr::get($details, 'initials'),
                'value' => Arr::get($details, 'value'),
            ],
            'manual_control_acquiring' => $this->manual_control === null ? null : [
                'confirmation_type' => $this->manual_control->confirmationType,
                'reject_reason' => $this->manual_control->rejectReason,
            ],
            'dispute' => [
                'status' => $this->dispute_status?->value,
                'reason' => $this->dispute_reason,
                'canceled_at' => $this->dispute_canceled_at?->getTimestamp(),
            ],
            'callback_url' => $this->callback_url,
            'finished_at' => $this->finished_at?->getTimestamp(),
            'created_at' => $this->created_at->getTimestamp(),
            'current_server_time' => now()->getTimestamp(),
        ];
    }
}
