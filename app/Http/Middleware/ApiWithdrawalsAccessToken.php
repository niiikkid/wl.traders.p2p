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
        $expectedToken = (string) config('api.api_withdraw_token', '');
        $token = (string) $request->header('Access-Token', '');

        if ($expectedToken === '' || $token === '' || ! hash_equals($expectedToken, $token)) {
            return response()->failWithMessage('Invalid Access Token.');
        }

        return $next($request);
    }
}
