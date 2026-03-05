<?php

namespace App\Http\Resources;

use App\Models\MerchantClient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantClientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var MerchantClient $this
         */
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'merchant_id' => $this->merchant_id,
            'merchant' => $this->whenLoaded('merchant', function () {
                return [
                    'id' => $this->merchant->id,
                    'name' => $this->merchant->name,
                    'uuid' => $this->merchant->uuid,
                ];
            }),
            'success_orders_count' => (int) ($this->success_orders_count ?? 0),
            'total_orders_count' => (int) ($this->total_orders_count ?? 0),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
