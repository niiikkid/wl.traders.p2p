<?php

namespace App\Http\Controllers\API\APP;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\UserDeviceResource;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function connect(Request $request)
    {
        $validated = $request->validate([
            'android_id' => 'required|string',
            'device_model' => 'required|string',
            'android_version' => 'required|string',
            'manufacturer' => 'required|string',
            'brand' => 'required|string',
            'device_connect_snapshot' => ['sometimes', 'nullable', 'string', 'max:1048576'],
        ]);

        $device = services()->device()->get($request->header('Access-Token'));

        if ($device->connected_at && $device->android_id !== $validated['android_id']) {
            return response()->failWithMessage('Этот токен уже привязан к другому устройству');
        }

        $snapshot = $validated['device_connect_snapshot'] ?? null;
        if (! is_string($snapshot) || $snapshot === '') {
            $snapshot = null;
        }

        $device = services()->device()->update(
            $device,
            android_id: $validated['android_id'],
            device_model: $validated['device_model'],
            android_version: $validated['android_version'],
            manufacturer: $validated['manufacturer'],
            brand: $validated['brand'],
            device_connect_snapshot: $snapshot,
        );

        return response()->success(
            new UserDeviceResource($device)
        );
    }

    public function ping(Request $request)
    {
        $device = services()->device()->get($request->header('Access-Token'));

        if (! $device->android_id) {
            return response()->failWithMessage('Устройство не подключено', 401);
        }

        services()->device()->ping($device);

        return response()->success();
    }
}
