<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserDeviceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'android_id' => $this->android_id,
            'device_model' => $this->device_model,
            'android_version' => $this->android_version,
            'manufacturer' => $this->manufacturer,
            'brand' => $this->brand,
            'connected_at' => $this->connected_at?->toDateTimeString(),
        ];
    }
}
