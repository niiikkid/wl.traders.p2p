<?php

namespace App\Http\Controllers\API\APP;

use App\Enums\DisputeStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\API\UserDeviceResource;
use App\Models\Dispute;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function connect(Request $request)
    {
        $request->validate([
            'android_id' => 'required|string',
            'device_model' => 'required|string',
            'android_version' => 'required|string',
            'manufacturer' => 'required|string',
            'brand' => 'required|string',
        ]);

        $device = services()->device()->get($request->header('Access-Token'));

        if ($device->connected_at && $device->android_id !== $request->android_id) {
            return response()->failWithMessage('Этот токен уже привязан к другому устройству');
        } elseif ($device->connected_at && $device->android_id === $request->android_id) {
            return response()->success(
                new UserDeviceResource($device)
            );
        }

        $device = services()->device()->update(
            $device,
            android_id: $request->android_id,
            device_model: $request->device_model,
            android_version: $request->android_version,
            manufacturer: $request->manufacturer,
            brand: $request->brand,
        );

        return response()->success(
            new UserDeviceResource($device)
        );
    }

    public function ping(Request $request)
    {
        $device = services()->device()->get($request->header('Access-Token'));

        if (!$device->android_id) {
            return response()->failWithMessage('Устройство не подключено', 401);
        }

        services()->device()->ping($device);

        return response()->success();
    }
}
