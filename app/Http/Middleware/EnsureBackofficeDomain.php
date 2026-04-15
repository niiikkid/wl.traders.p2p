<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBackofficeDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $appHost = config('domains.app_host');
        if (!is_string($appHost) || $appHost === '') {
            return $next($request);
        }

        $currentHost = $request->getHost();

        if (config('domains.split_marketing')) {
            if (strtolower($currentHost) !== strtolower($appHost)) {
                abort(404);
            }

            return $next($request);
        }

        $paymentHost = config('domains.payment_host');
        $paymentHostStr = is_string($paymentHost) ? $paymentHost : '';

        // Одна зона: ограничение выключено, если платёжный хост не задан или совпадает с APP.
        if ($paymentHostStr === '' || strtolower($appHost) === strtolower($paymentHostStr)) {
            return $next($request);
        }

        if (strtolower($currentHost) !== strtolower($appHost)) {
            abort(404);
        }

        return $next($request);
    }
}
