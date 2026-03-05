<?php

namespace App\Http\Resources;

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
            'id' => $this->id,
            'name' => $this->name_with_currency,
            'original_name' => $this->name,
            'code' => $this->code,
            'detail_types' => $this->detail_types,
            'currency' => $this->currency->getCode(),
            'min_limit' => $this->min_limit,
            'max_limit' => $this->max_limit,
            'sms_senders' => $this->sms_senders,
            'trader_commission_rate_for_orders' => $this->trader_commission_rate_for_orders,
            'total_service_commission_rate_for_orders' => $this->total_service_commission_rate_for_orders,
            'trader_commission_rate_for_payouts' => $this->trader_commission_rate_for_payouts,
            'total_service_commission_rate_for_payouts' => $this->total_service_commission_rate_for_payouts,
            'is_active' => $this->is_active,
            'is_payouts_enabled' => (bool) $this->is_payouts_enabled,
            'is_intrabank' => $this->is_intrabank,
            'reservation_time_for_orders' => $this->reservation_time_for_orders,
            'reservation_time_for_payouts' => $this->reservation_time_for_payouts,
            'logo_path' => $this->logo ? asset('storage/logos/'.$this->logo) : null,
        ];
    }
}
