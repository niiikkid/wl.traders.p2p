<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'idempotency' => \Square1\LaravelIdempotency\Http\Middleware\IdempotencyMiddleware::class,
            'idempotency_for_app' => \App\Http\Middleware\IdempotencyForAppMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'banned' => \App\Http\Middleware\Banned::class,
            'api-access-token' => \App\Http\Middleware\ApiAccessToken::class,
            'api-bot-access-token' => \App\Http\Middleware\ApiBotAccessToken::class,
            'api-deposits-access-token' => \App\Http\Middleware\ApiDepositsAccessToken::class,
            'api-withdrawals-access-token' => \App\Http\Middleware\ApiWithdrawalsAccessToken::class,
            'device-access-token' => \App\Http\Middleware\DeviceAccessToken::class,
            '2fa' => \App\Http\Middleware\Google2FAMiddleware::class,
            'telegram.secret' => \App\Http\Middleware\VerifyTelegramSecretToken::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'telegram/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        \Sentry\Laravel\Integration::handles($exceptions);
    })
    ->withEvents(discover: [
        __DIR__.'/../app/Listeners',
    ])
    ->create();
