<?php

namespace App\Http\Resources\API;

use App\Models\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentGatewayResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var PaymentGateway $this
         */
        return [
            'name' => $this->name,
            'code' => $this->code,
            'currency' => $this->currency->getCode(),
            'min_limit' => $this->min_limit,
            'max_limit' => $this->max_limit,
            'reservation_time' => $this->reservation_time_for_orders,
            'detail_types' => $this->detail_types,
        ];
    }
}
