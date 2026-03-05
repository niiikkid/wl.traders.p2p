<?php

namespace App\Http\Middleware;

use App\Models\UserDevice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DeviceAccessToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('Access-Token');

        if (!$token) {
            return response()->failWithMessage('Токен устройства не указан', 401);
        }

        $device = services()->device()->get($token);

        if (!$device) {
            return response()->failWithMessage('Неверный токен устройства', 401);
        }

        $request->merge(['device' => $device]);

        return $next($request);
    }
}
