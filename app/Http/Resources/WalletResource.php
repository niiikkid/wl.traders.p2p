<?php

namespace App\Http\Resources;

use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var Wallet $this
         */
        return [
            'id' => $this->id,
            'merchant_balance' => $this->merchant_balance->toBeauty(),
            'trust_balance' => $this->trust_balance->toBeauty(),
            'provider_balance' => $this->provider_balance?->toBeauty(),
            'agent_balance' => $this->agent_balance?->toBeauty(),
            'reserve_balance' => $this->reserve_balance->toBeauty(),
            'currency' => $this->merchant_balance->getCurrency()->getCode(),
            'user_id' => $this->user_id,
            'merchant_id' => $this->merchant_id,
            'merchant' => $this->merchant ? [
                'id' => $this->merchant->id,
                'uuid' => $this->merchant->uuid,
                'name' => $this->merchant->name,
            ] : null,
        ];
    }
}
