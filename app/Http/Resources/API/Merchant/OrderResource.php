<?php

namespace App\Http\Resources\API\Merchant;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'merchant_id' => $this->merchant->uuid,
            'base_amount' => $this->base_amount->toBeauty(),
            'amount' => $this->amount->toBeauty(),
            'currency' => $this->currency->getCode(),
            'status' => $this->status->value,
            'sub_status' => $this->sub_status->value,
            'callback_url' => $this->callback_url,
            'success_url' => $this->success_url,
            'fail_url' => $this->fail_url,
            'payment_gateway' => $this->paymentGateway?->code,
            'payment_gateway_name' => $this->paymentGateway?->name,
            'finished_at' => $this->finished_at?->getTimestamp(),
            'expires_at' => $this->expires_at?->getTimestamp(),
            'created_at' => $this->created_at->getTimestamp(),
            'payment_link' => route('payment.show', $this->uuid),
        ];
    }
}
