<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var Invoice $this
         */
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'amount' => $this->amount->toBeauty(),
            'currency' => $this->currency->getCode(),
            'type' => $this->type->value,
            'balance_type' => $this->balance_type->value,
            'address' => $this->address,
            'network' => $this->network?->value,
            'tx_hash' => $this->tx_hash,
            'status' => $this->status->value,
            'user' => [
               'id' => $this->wallet->user->id,
               'email' => $this->wallet->user->email,
            ],
            'wallet_id' => $this->wallet_id,
            'transaction_id' => $this->transaction_id,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
