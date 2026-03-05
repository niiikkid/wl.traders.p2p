<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Money\Currency;
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
        $shotUUID = mb_substr($this->uuid, 0, 8);
        /**
         * @var Order $this
         */
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'uuid_short' => $shotUUID,
            'external_id' => $this->external_id,
            'base_amount' => $this->base_amount->toBeauty(),
            'amount' => $this->amount->toBeauty(),
            'total_profit' => $this->total_profit->toBeauty(),
            'trader_profit' => $this->trader_profit->toBeauty(),
            'team_leader_profit' => $this->team_leader_profit->toBeauty(),
            'merchant_profit' => $this->merchant_profit->toBeauty(),
            'service_profit' => $this->service_profit->toBeauty(),
            'trader_paid_for_order' => $this->trader_paid_for_order?->toBeauty(),
            'base_conversion_price' => $this->conversion_price->toBeauty(),
            'conversion_price' => $this->conversion_price->toBeauty(),
            'trader_commission_rate' => $this->trader_commission_rate,
            'team_leader_commission_rate' => $this->team_leader_commission_rate,
            'total_service_commission_rate' => $this->total_service_commission_rate,
            'service_commission_amount_total' => (float)$this->total_profit
                ->mul($this->total_service_commission_rate / 100)
                ->toBeauty(),
            'currency' => $this->currency->getCode(),
            'base_currency' => Currency::USDT()->getCode(),
            'status' => $this->status->value,
            'status_name' => $this->status_name,
            'callback_url' => $this->callback_url,
            'is_h2h' => $this->is_h2h,
            $this->mergeWhen(auth()->check() && auth()->user()->hasRole('Super Admin'), function () {
                return [
                    'amount_updates_history' => $this->amount_updates_history ? array_reverse($this->amount_updates_history) : null,
                    'total_fee' => $this->total_fee?->toBeauty(),
                    'trader_receive' => $this->trader_receive?->toBeauty(),
                    'merchant_credit' => $this->merchant_credit?->toBeauty(),
                    'team_leader_split_from_service_percent' => $this->team_leader_split_from_service_percent,
                ];
            }),
            $this->mergeWhen($this->resource->relationLoaded('paymentGateway'), function () {
                return [
                    'payment_gateway_code' => $this->paymentGateway?->code,
                    'payment_gateway_name' => $this->paymentGateway?->name_with_currency,
                    'payment_gateway_logo_path' => $this->paymentGateway?->logo ? asset('storage/logos/'.$this->paymentGateway->logo) : null,
                ];
            }),
            $this->mergeWhen($this->resource->relationLoaded('paymentGateway'), function () {
                return [
                    'payment_gateway_code' => $this->paymentGateway->code,
                    'payment_gateway_name' => $this->paymentGateway->name_with_currency,
                ];
            }),
            $this->mergeWhen($this->resource->relationLoaded('paymentDetail'), function () {
                return [
                    'payment_detail' => $this->paymentDetail?->detail,
                    'payment_detail_type' => $this->paymentDetail?->detail_type->value,
                    'payment_detail_name' => $this->paymentDetail?->name,
                ];
            }),
            $this->mergeWhen($this->resource->relationLoaded('trader'), function () {
                return [
                    'user' => [
                        'id' => $this->trader->id,
                        'name' => $this->trader->name,
                        'email' => $this->trader->email,
                    ]
                ];
            }),
            $this->mergeWhen($this->resource->relationLoaded('teamLeader') && $this->teamLeader, function () {
                return [
                    'team_leader' => [
                        'id' => $this->teamLeader->id,
                        'name' => $this->teamLeader->name,
                        'email' => $this->teamLeader->email,
                    ]
                ];
            }),
            $this->mergeWhen($this->resource->relationLoaded('smsLog') && $this->smsLog, function () {
                return [
                    'sms_log' => [
                        'sender' => $this->smsLog->sender,
                        'message' => $this->smsLog->message,
                        'created_at' => $this->smsLog->created_at->toISOString(),
                    ]
                ];
            }),
            $this->mergeWhen($this->resource->relationLoaded('merchant'), function () {
                return [
                    'merchant' => [
                        'id' => $this->merchant->id,
                        'name' => $this->merchant->name,
                    ],
                ];
            }),
            'has_dispute' => $this->dispute_exists,
            'expires_at' => $this->expires_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'payment_link' => route('payment.show', $this->uuid),
            'canEditAmount' => $this->status->equals(OrderStatus::PENDING) && $this->dispute_exists && $this->trader_paid_for_order,
        ];
    }
}
