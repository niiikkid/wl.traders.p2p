<?php

namespace App\Http\Resources;

use App\Models\UserDevice;
use Carbon\Carbon;
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
            'connected_at' => $this->connected_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'latest_ping_at' => $this->normalizeCachedDate(cache()->get('user-device-latest-ping-at-' . $this->id)),
        ];
    }

    private function normalizeCachedDate(mixed $date): ?string
    {
        if (!is_string($date) || $date === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->toISOString();
        } catch (\Throwable) {
            return null;
        }
    }
} 