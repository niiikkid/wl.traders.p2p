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
            'uuid' => $this->uuid,
            'uuid_short' => mb_substr($this->uuid, 0, 8),
            'receipt' => $this->receipt,
            'receipt_url' => $this->receipt ? route('disputes.receipt', $this->uuid) : null,
            'bank_statement' => $this->bank_statement,
            'bank_statement_url' => $this->bank_statement ? route('disputes.bank-statement', $this->uuid) : null,
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
                'uuid' => $this->order->paymentDetail->uuid,
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
                'logo_path' => $this->order->paymentGateway->logoUrl(),
            ],
            'status' => $this->status->value,
            'reason' => $this->reason,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
