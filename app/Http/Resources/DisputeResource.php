<?php

namespace App\Http\Resources;

use App\Models\Dispute;
use App\Services\Money\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisputeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var Dispute $this
         */
        return [
            'id' => $this->id,
            'receipt' => $this->receipt,
            'receipt_url' => $this->receipt ? route('disputes.receipt', $this->id) : null,
            'order' => [
                'id' => $this->order->id,
                'uuid' => $this->order->uuid,
                'amount' => $this->order->amount->toBeauty(),
                'total_profit' => $this->order->total_profit->toBeauty(),
                'currency' => $this->order->currency->getCode(),
                'base_currency' => Currency::USDT()->getCode(),
                'status' => $this->order->status,
                'status_name' => $this->order->status_name,
                'created_at' => $this->order->created_at->toDateTimeString(),
            ],
            'payment_detail' => [
                'id' => $this->order->paymentDetail->id,
                'detail' => $this->order->paymentDetail->detail,
                'type' => $this->order->paymentDetail->detail_type->value,
                'name' => $this->order->paymentDetail->name,
            ],
            'user' => [
                'id' => $this->order->paymentDetail->user->id,
                'name' => $this->order->paymentDetail->user->name,
                'email' => $this->order->paymentDetail->user->email,
            ],
            'payment_gateway' => [
                'name' => $this->order->paymentGateway->name,
                'logo_path' => asset('storage/logos/'.$this->order->paymentGateway->logo),
            ],
            'status' => $this->status->value,
            'reason' => $this->reason,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
