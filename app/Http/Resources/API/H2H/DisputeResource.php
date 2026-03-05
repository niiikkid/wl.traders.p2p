<?php

namespace App\Http\Resources\API\H2H;

use App\Models\Dispute;
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
            'order_id' => $this->order->uuid,
            'status' => $this->status->value,
            'cancel_reason' => $this->reason,
        ];
    }
}
