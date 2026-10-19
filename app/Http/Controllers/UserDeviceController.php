<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserDeviceResource;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserDeviceController extends Controller
{
    /**
     * Отображает список устройств пользователя
     *
     * @return Response
     */
    public function index()
    {
        $devices = Auth::user()
            ->devices()
            ->orderBy('created_at', 'desc')
            ->get();
        $devices = UserDeviceResource::collection($devices);

        $smsAutoCloseEnabled = (bool) Auth::user()->sms_auto_close_orders_enabled;

        return Inertia::render('UserDevice/Index', compact('devices', 'smsAutoCloseEnabled'));
    }

    public function connectSnapshot(UserDevice $device): JsonResponse
    {
        Gate::authorize('access-to-user-device-snapshot', $device);

        return response()->success([
            'device_id' => $device->id,
            'has_snapshot' => filled($device->device_connect_snapshot),
            'device_connect_snapshot' => $device->device_connect_snapshot,
            'updated_at' => $device->updated_at?->toISOString(),
        ]);
    }

    /**
     * Создает новое устройство
     *
     * @return RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        services()->device()->create(Auth::id(), $request->name);

        return redirect()->route('trader.devices.index')->with('success', 'Токен для устройства успешно создан');
    }

    public function updateSmsProcessingMode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sms_auto_close_orders_enabled' => ['required', 'boolean'],
        ]);

        Auth::user()->update([
            'sms_auto_close_orders_enabled' => (bool) $validated['sms_auto_close_orders_enabled'],
        ]);

        return redirect()
            ->route('trader.devices.index')
            ->with('success', 'Режим обработки SMS успешно обновлен');
    }
}
