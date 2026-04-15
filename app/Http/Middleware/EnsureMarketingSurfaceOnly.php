<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * На маркетинговом хосте разрешены только лендинг и (при совпадении с платёжным) публичные /payment/*.
 * Остальные совпавшие маршруты перенаправляются на APP_URL.
 */
class EnsureMarketingSurfaceOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('domains.split_marketing')) {
            return $next($request);
        }

        $marketing_host = config('domains.marketing_host');
        if (!is_string($marketing_host) || $marketing_host === '') {
            return $next($request);
        }

        if (strtolower($request->getHost()) !== strtolower($marketing_host)) {
            return $next($request);
        }

        if ($this->isAllowedOnMarketingHost($request)) {
            return $next($request);
        }

        $app_url = rtrim((string) config('domains.app_url', ''), '/');
        if ($app_url === '') {
            abort(404);
        }

        $target = $app_url.$request->getRequestUri();
        $status = $request->isMethodSafe() ? 302 : 307;

        return redirect()->away($target, $status);
    }

    private function isAllowedOnMarketingHost(Request $request): bool
    {
        if ($request->routeIs('landing.home')) {
            return true;
        }

        $route_name = $request->route()?->getName();
        if ($this->paymentSharesMarketingHost()
            && is_string($route_name)
            && str_starts_with($route_name, 'payment.')
        ) {
            return true;
        }

        if ($request->is('sanctum/csrf-cookie')) {
            return true;
        }

        if ($request->is('up') && $request->isMethod('GET')) {
            return true;
        }

        if (app()->isLocal() && $request->is('_debugbar*')) {
            return true;
        }

        return false;
    }

    private function paymentSharesMarketingHost(): bool
    {
        $payment_host = config('domains.payment_host');
        $marketing_host = config('domains.marketing_host');

        return is_string($payment_host) && is_string($marketing_host)
            && strtolower($payment_host) === strtolower($marketing_host);
    }
}
