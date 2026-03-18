<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBackofficeDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $appHost = (string) config('domains.app_host');
        $paymentHost = (string) config('domains.payment_host');
        $currentHost = $request->getHost();

        // Если payment и backoffice на одном домене, ограничение выключено.
        if (!$appHost || $appHost === $paymentHost) {
            return $next($request);
        }

        if ($currentHost !== $appHost) {
            abort(404);
        }

        return $next($request);
    }
}
