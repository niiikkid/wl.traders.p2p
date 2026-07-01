<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Google2FAMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if ($user->isImpersonated()) {
            if (session()->has('user_2fa_passed') || $user->google2fa_secret === null) {
                return $next($request);
            }

            return redirect()->route('auth.2fa');
        }

        services()->accountSession()->ensureCurrentAccount($request, $user);

        if (! services()->accountSession()->requiresTwoFactor($request, $user)) {
            return $next($request);
        }

        return redirect()->route('auth.2fa');
    }
}
