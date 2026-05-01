<?php

namespace App\Http\Middleware;

use App\Facades\LoginLogger;
use App\Models\MerchantApiCredential;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApiV2AccessToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->header('Access-Token', '');

        $credential = cache()->remember("api-v2-access-token-middleware-$token", 60 * 60 * 24, function () use ($token) {
            return MerchantApiCredential::query()
                ->with('merchant.user')
                ->where('api_token', $token)
                ->first();
        });

        $credential = $credential?->fresh(['merchant.user']);
        $user = $credential?->merchant?->user;

        if (! $credential || $credential->api_token !== $token || ! $credential->merchant || ! $user || $user->archived_at !== null) {
            cache()->forget("api-v2-access-token-middleware-$token");

            return response()->failWithMessage('Invalid Access Token.');
        }

        LoginLogger::disable();
        Auth::login($user);
        LoginLogger::enable();

        $request->attributes->set('merchant_api_credential', $credential);

        return $next($request);
    }
}
