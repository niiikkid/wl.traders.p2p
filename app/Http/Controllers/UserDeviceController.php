<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserDeviceResource;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UserDeviceController extends Controller
{
    /**
     * Отображает список устройств пользователя
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        if (Auth::user()->can_work_without_device) {
            abort(403);
        }

        $devices = Auth::user()
            ->devices()
            ->orderBy('created_at', 'desc')
            ->get();
        $devices = UserDeviceResource::collection($devices);

        return Inertia::render('UserDevice/Index', compact('devices'));
    }

    /**
     * Создает новое устройство
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        if (Auth::user()->can_work_without_device) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        services()->device()->create(Auth::id(), $request->name);

        return redirect()->route('trader.devices.index')->with('success', 'Токен для устройства успешно создан');
    }
}
