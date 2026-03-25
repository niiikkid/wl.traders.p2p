<?php

namespace App\Http\Middleware;

use App\Facades\LoginLogger;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApiAccessToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('Access-Token');

        $user = cache()->remember("api-access-token-middleware-$token", 60 * 60 * 24, function () use ($token) {
            return User::query()
                ->where('api_access_token', $token)
                ->whereNull('archived_at')
                ->first();
        });

        $user = $user?->fresh();

        if (! $user || $user->archived_at !== null) {
            cache()->forget("api-access-token-middleware-$token");
            return response()->failWithMessage('Invalid Access Token.');
        }

        LoginLogger::disable();
        Auth::login($user);
        LoginLogger::enable();

        return $next($request);
    }
}
