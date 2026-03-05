<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var Transaction $this
         */
        return [
            'id' => $this->id,
            'amount' => $this->amount->toBeauty(),
            'currency' => $this->amount->getCurrency()->getCode(),
            'direction' => $this->direction->value,
            'type' => $this->type->value,
            'type_name' => trans('transaction-type.'.$this->type->value),
            'balance_type' => $this->balance_type->value,
            'wallet_id' => $this->wallet_id,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
