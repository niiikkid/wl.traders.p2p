<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserDeviceResource;
use App\Models\UserDevice;
use Inertia\Inertia;
use Inertia\Response;

class UserDeviceController extends Controller
{
    public function index(): Response
    {
        $devices = UserDevice::query()
            ->with('user:id,email,sms_auto_close_orders_enabled')
            ->orderByDesc('id')
            ->paginate(request()->per_page ?? 10);

        $devices = UserDeviceResource::collection($devices);

        return Inertia::render('Admin/UserDevice/Index', compact('devices'));
    }
}
