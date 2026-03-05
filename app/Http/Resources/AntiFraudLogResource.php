<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AntiFraudLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'merchant' => $this->when($this->merchant, function () {
                return [
                    'id' => $this->merchant->id,
                    'name' => $this->merchant->name,
                    'uuid' => $this->merchant->uuid,
                ];
            }),
            'client_id' => $this->client_id,
            'decision' => $this->decision,
            'message' => $this->message,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
