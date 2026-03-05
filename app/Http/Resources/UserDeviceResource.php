<?php

namespace App\Http\Resources;

use App\Models\UserDevice;
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
        /**
         * @var UserDevice $this
         */
        return [
            'id' => $this->id,
            'name' => $this->name,
            'token' => $this->token,
            'android_id' => $this->android_id,
            'device_model' => $this->device_model,
            'android_version' => $this->android_version,
            'manufacturer' => $this->manufacturer,
            'brand' => $this->brand,
            'connected_at' => $this->connected_at?->toDateTimeString(),
            'created_at' => $this->created_at->toDateTimeString(),
            'latest_ping_at' => cache()->get('user-device-latest-ping-at-' . $this->id),
        ];
    }
} 