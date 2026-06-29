<?php

namespace App\Http\Resources;

use App\Models\UserDevice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserDeviceResource extends JsonResource
{
    private const ONLINE_THRESHOLD_SECONDS = 30;

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
        $latestPingAt = $this->normalizeCachedDate(cache()->get('user-device-latest-ping-at-'.$this->id));
        $isConnected = filled($this->android_id);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'token' => $this->token,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'email' => $this->user->email,
                'sms_auto_close_orders_enabled' => (bool) $this->user->sms_auto_close_orders_enabled,
            ]),
            'android_id' => $this->android_id,
            'device_model' => $this->device_model,
            'android_version' => $this->android_version,
            'manufacturer' => $this->manufacturer,
            'brand' => $this->brand,
            'is_connected' => $isConnected,
            'is_online' => $isConnected && $this->isOnline($latestPingAt),
            'hardware_title' => $isConnected ? $this->hardwareTitle() : null,
            'android_label' => $isConnected && filled($this->android_version)
                ? 'Android '.$this->android_version
                : null,
            'has_connect_snapshot' => (bool) ($this->getAttributes()['has_connect_snapshot'] ?? filled($this->device_connect_snapshot)),
            'connected_at' => $this->connected_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'latest_ping_at' => $latestPingAt,
        ];
    }

    private function isOnline(?string $latestPingAt): bool
    {
        if ($latestPingAt === null) {
            return false;
        }

        try {
            return Carbon::parse($latestPingAt)->gte(
                now()->subSeconds(self::ONLINE_THRESHOLD_SECONDS)
            );
        } catch (\Throwable) {
            return false;
        }
    }

    private function hardwareTitle(): ?string
    {
        $brand = trim((string) ($this->manufacturer ?: $this->brand ?: ''));
        $model = trim((string) ($this->device_model ?: ''));

        if ($brand !== '' && $model !== '') {
            return $brand.' '.$model;
        }

        if ($model !== '') {
            return $model;
        }

        if ($brand !== '') {
            return $brand;
        }

        return null;
    }

    private function normalizeCachedDate(mixed $date): ?string
    {
        if (! is_string($date) || $date === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->toISOString();
        } catch (\Throwable) {
            return null;
        }
    }
}
