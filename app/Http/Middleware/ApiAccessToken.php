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
            return User::where('api_access_token', $token)->first();
        });

        if (! $user) {
            return response()->failWithMessage('Invalid Access Token.');
        }

        LoginLogger::disable();
        Auth::login($user);
        LoginLogger::enable();

        return $next($request);
    }
}
