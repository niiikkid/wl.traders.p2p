<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\TelegramChatBotServiceContract;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTelegramChatAutomationSecretToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $setting = app(TelegramChatBotServiceContract::class)->getSettings();
        $secret = $setting->webhook_secret;

        if (! is_string($secret) || $secret === '') {
            abort(403);
        }

        $header = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (! is_string($header) || ! hash_equals($secret, $header)) {
            abort(403);
        }

        return $next($request);
    }
}
