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
            'initial_amount' => $this->base_amount->toBeauty(),
            'amount' => $this->amount->toBeauty(),
            'total_profit' => $this->total_profit->toBeauty(),
            'merchant_profit' => $this->merchant_profit->toBeauty(),
            'currency' => $this->currency->getCode(),
            'profit_currency' => $this->total_profit->getCurrency()->getCode(),
            'rate_currency' => $this->conversion_price->getCurrency()->getCode(),
            'rate' => $this->conversion_price->toBeauty(),
            'status' => $this->status->value,
            'sub_status' => $this->sub_status->value,
            'reject_reason' => $this->manual_control_reject_reason,
            'callback_url' => $this->callback_url,
            'manual_control_acquiring' => (bool) $this->manual_control_acquiring,
            'manual_control_confirmation_type' => $this->manual_control_confirmation_type?->value,
            'payment_gateway' => $this->paymentGateway?->code,
            'payment_gateway_name' => $this->paymentGateway?->name,
            'payment_detail' => [
                'requisites' => $this->manual_control_acquiring ? null : $this->paymentDetail?->detail,
                'type' => $this->manual_control_acquiring ? null : $this->paymentDetail?->detail_type,
                'holder_name' => $this->manual_control_acquiring ? null : $this->paymentDetail?->initials,
                'dispute' => $this->whenLoaded('dispute', function () {
                    return [
                        'status' => $this->dispute?->status->value,
                        'reason' => $this->dispute?->reason,
                    ];
                }),
            ],
            'merchant' => [
                'name' => $merchant->name,
                'description' => $merchant->description,
            ],
            'finished_at' => $this->finished_at?->toIso8601String(),
            'expires_at' => $this->expires_at->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'current_server_time' => now()->toIso8601String(),
        ];
    }
}
