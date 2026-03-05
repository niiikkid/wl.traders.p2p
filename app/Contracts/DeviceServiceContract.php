<?php

namespace App\Contracts;

use App\Models\UserDevice;

interface DeviceServiceContract
{
    public function get(string $token): ?UserDevice;

    public function create(int $user_id, string $name): UserDevice;

    public function update(UserDevice $device, string $android_id, string $device_model, string $android_version, string $manufacturer, string $brand): UserDevice;

    public function ping(UserDevice $device): void;
}
