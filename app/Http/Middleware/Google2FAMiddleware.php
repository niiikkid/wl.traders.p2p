<?php

namespace App\Http\Middleware;

use Closure;
use PragmaRX\Google2FALaravel\Events\OneTimePasswordRequested;
use PragmaRX\Google2FALaravel\Support\Authenticator;

class Google2FAMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle($request, Closure $next)
    {
        $user = auth()->user();

        if (!$user) {
            return $next($request);
        }

        if (session()->has('user_2fa_passed') || $user->google2fa_secret === null) {
            return $next($request);
        }

        return redirect()->route('auth.2fa');
    }
}
