<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserDeviceResource;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserDeviceController extends Controller
{
    public function index(): Response
    {
        $devices = UserDevice::query()
            ->with('user:id,email,sms_auto_close_orders_enabled')
            ->select([
                'user_devices.id',
                'user_devices.user_id',
                'user_devices.name',
                'user_devices.token',
                'user_devices.android_id',
                'user_devices.device_model',
                'user_devices.android_version',
                'user_devices.manufacturer',
                'user_devices.brand',
                'user_devices.connected_at',
                'user_devices.created_at',
                'user_devices.updated_at',
            ])
            ->selectRaw('(user_devices.device_connect_snapshot IS NOT NULL AND user_devices.device_connect_snapshot != "") as has_connect_snapshot')
            ->orderByDesc('user_devices.id')
            ->paginate(request()->per_page ?? 10);

        $devices = UserDeviceResource::collection($devices);

        return Inertia::render('Admin/UserDevice/Index', compact('devices'));
    }

    public function connectSnapshot(UserDevice $device): JsonResponse
    {
        $device = UserDevice::query()
            ->select(['id', 'device_connect_snapshot', 'updated_at'])
            ->findOrFail($device->id);

        return response()->success([
            'device_id' => $device->id,
            'has_snapshot' => filled($device->device_connect_snapshot),
            'device_connect_snapshot' => $device->device_connect_snapshot,
            'updated_at' => $device->updated_at?->toISOString(),
        ]);
    }
}
