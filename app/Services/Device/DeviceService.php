<?php

namespace App\Services\Device;

use App\Contracts\DeviceServiceContract;
use App\Models\UserDevice;
use App\Models\UserDevicePing;

class DeviceService implements DeviceServiceContract
{
    public function create(int $user_id, string $name): UserDevice
    {
        $device = new UserDevice();
        $device->user_id = $user_id;
        $device->name = $name;
        $device->token = UserDevice::generateToken();
        $device->save();

        return $device;
    }

    public function get(string $token): ?UserDevice
    {
        return cache()->remember(
            'device_by_token_' . $token,
            now()->addMinutes(10),
            function () use ($token) {
                return UserDevice::where('token', $token)->first();
            }
        );
    }

    public function update(UserDevice $device, string $android_id, string $device_model, string $android_version, string $manufacturer, string $brand): UserDevice
    {
        $device->update([
            'android_id' => $android_id,
            'device_model' => $device_model,
            'android_version' => $android_version,
            'manufacturer' => $manufacturer,
            'brand' => $brand,
            'connected_at' => now(),
        ]);

        cache()->forget('device_by_token_' . $device->token);

        return $device;
    }

    /**
     * Зафиксировать пинг устройства: обновить кеш пользователя и записать бакет в БД (idempotent на 5с).
     */
    public function ping(UserDevice $device): void
    {
        $now = now();
        $user = $device->user;

        cache()->put("user-apk-latest-ping-at-$user->id", $now->toDateTimeString());
        cache()->put('user-device-latest-ping-at-' . $device->id, $now->toDateTimeString());

        $bucket = UserDevicePing::toBucket5s($now);
        UserDevicePing::query()->updateOrCreate(
            [
                'user_device_id' => $device->id,
                'bucket_5s' => $bucket,
            ],
            []
        );
    }
}
