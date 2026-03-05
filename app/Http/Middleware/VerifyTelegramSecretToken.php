<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyTelegramSecretToken
{
    public function handle(Request $request, Closure $next)
    {
        $secret = config('telegram.webhook_secret');

        if ($secret) {
            $header = $request->header('X-Telegram-Bot-Api-Secret-Token');

            if (! $header || ! hash_equals($secret, $header)) {
                abort(403);
            }
        }

        return $next($request);
    }
}
