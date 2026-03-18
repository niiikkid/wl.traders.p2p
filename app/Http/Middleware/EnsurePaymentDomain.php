<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePaymentDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $paymentUrl = rtrim((string) config('domains.payment_url', config('app.url')), '/');
        $paymentHost = (string) config('domains.payment_host');
        $currentHost = $request->getHost();

        if (!$paymentHost || $currentHost === $paymentHost) {
            return $next($request);
        }

        $redirectUrl = $paymentUrl.$request->getRequestUri();
        $statusCode = $request->isMethodSafe()
            ? (int) config('domains.legacy_redirect_status', 301)
            : 308;

        return redirect()->to($redirectUrl, $statusCode);
    }
}
