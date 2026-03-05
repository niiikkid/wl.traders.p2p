<?php

namespace App\Http\Resources\API\H2H;

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

        $merchant = queries()->merchant()->findByID($this->merchant_id);

        return [
            'order_id' => $this->uuid,
            'external_id' => $this->external_id,
            'merchant_id' => $merchant->uuid,
            'base_amount' => $this->base_amount->toBeauty(),
            'amount' => $this->amount->toBeauty(),
            'profit' => $this->total_profit->toBeauty(),
            'merchant_profit' => $this->merchant_profit->toBeauty(),
            'currency' => $this->currency->getCode(),
            'profit_currency' => $this->total_profit->getCurrency()->getCode(),
            'conversion_price_currency' => $this->conversion_price->getCurrency()->getCode(),
            'conversion_price' => $this->conversion_price->toBeauty(),
            'status' => $this->status->value,
            'sub_status' => $this->sub_status->value,
            'callback_url' => $this->callback_url,
            'payment_gateway' => $this->paymentGateway->code,
            'payment_gateway_name' => $this->paymentGateway->name,
            'payment_detail' => [
                'detail' => $this->paymentDetail->detail,
                'detail_type' => $this->paymentDetail->detail_type,
                'initials' => $this->paymentDetail->initials,
                'dispute' => $this->whenLoaded('dispute', function () {
                    return [
                        'status' => $this->dispute?->status->value,
                        'cancel_reason' => $this->dispute?->reason,
                    ];
                }),
            ],
            'merchant' => [
                'name' => $merchant->name,
                'description' => $merchant->description,
            ],
            'finished_at' => $this->finished_at?->getTimestamp(),
            'expires_at' => $this->expires_at->getTimestamp(),
            'created_at' => $this->created_at->getTimestamp(),
            'current_server_time' => now()->getTimestamp(),
        ];
    }
}
