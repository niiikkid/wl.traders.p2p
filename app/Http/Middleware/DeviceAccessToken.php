<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeviceAccessToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('Access-Token');

        if (! $token) {
            return response()->failWithMessage('Токен устройства не указан', 401);
        }

        $device = services()->device()->get($token);

        if (! $device) {
            return response()->failWithMessage('Неверный токен устройства', 401);
        }

        $user = $device->user;
        if (! $user || $user->archived_at !== null || $user->banned_at !== null) {
            return response()->failWithMessage('Пользователь устройства недоступен', 403);
        }

        $request->merge(['device' => $device]);

        return $next($request);
    }
}
