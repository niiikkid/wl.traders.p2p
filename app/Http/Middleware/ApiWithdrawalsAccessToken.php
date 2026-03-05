<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiWithdrawalsAccessToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('Access-Token');

        if (config('api.api_withdraw_token') !== $token) {
            return response()->failWithMessage('Invalid Access Token.');
        }

        return $next($request);
    }
}
