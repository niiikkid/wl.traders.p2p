<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Services\Money\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableOrderResource extends JsonResource
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
            'id' => $this->id,
            'uuid' => $this->uuid,
            'amount' => $this->amount->toBeauty(),
            'total_profit' => $this->total_profit->toBeauty(),
            'currency' => $this->currency->getCode(),
            'base_currency' => Currency::USDT()->getCode(),
            'status' => $this->status->value,
            'status_name' => $this->status_name,
            'has_dispute' => (bool) $this->dispute_exists,
            'dispute' => $this->dispute ? [
                'id' => $this->dispute->id,
                'receipt' => $this->dispute->receipt,
                'receipt_url' => $this->dispute->receipt ? route('disputes.receipt', $this->dispute->id) : null,
                'order' => [
                    'id' => $this->id,
                    'uuid' => $this->uuid,
                    'amount' => $this->amount->toBeauty(),
                    'total_profit' => $this->total_profit->toBeauty(),
                    'currency' => $this->currency->getCode(),
                    'base_currency' => Currency::USDT()->getCode(),
                    'status' => $this->status,
                    'status_name' => $this->status_name,
                    'created_at' => $this->created_at->toDateTimeString(),
                ],
                'payment_detail' => [
                    'id' => $this->paymentDetail->id,
                    'detail' => $this->paymentDetail->detail,
                    'type' => $this->paymentDetail->detail_type->value,
                    'name' => $this->paymentDetail->name,
                ],
                'user' => [
                    'id' => $this->paymentDetail->user->id,
                    'name' => $this->paymentDetail->user->name,
                    'email' => $this->paymentDetail->user->email,
                ],
                'payment_gateway' => [
                    'name' => $this->paymentGateway->name,
                    'logo_path' => asset('storage/logos/'.$this->paymentGateway->logo),
                ],
                'status' => $this->dispute->status->value,
                'reason' => $this->dispute->reason,
                'created_at' => $this->dispute->created_at->toDateTimeString(),
            ] : null,
            'payment_gateway_name' => $this->paymentGateway->name,
            'payment_gateway_logo_path' => asset('storage/logos/'.$this->paymentGateway->logo),
            'payment_detail' => $this->paymentDetail->detail,
            'payment_detail_type' => $this->paymentDetail->detail_type->value,
            'payment_detail_name' => $this->paymentDetail->name,
            'device_name' => $this->paymentDetail?->userDevice?->name,
            'trader_email' => $this->trader->email,
            'trader_name' => $this->trader->name,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
