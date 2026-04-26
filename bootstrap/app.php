<?php

use App\Http\Middleware\ApiAccessToken;
use App\Http\Middleware\ApiBotAccessToken;
use App\Http\Middleware\ApiDepositsAccessToken;
use App\Http\Middleware\ApiWithdrawalsAccessToken;
use App\Http\Middleware\Banned;
use App\Http\Middleware\DeviceAccessToken;
use App\Http\Middleware\EnsureBackofficeDomain;
use App\Http\Middleware\EnsureMarketingSurfaceOnly;
use App\Http\Middleware\EnsurePaymentDomain;
use App\Http\Middleware\Google2FAMiddleware;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdempotencyForAppMiddleware;
use App\Http\Middleware\IntegrationInfrastructureApiAccessToken;
use App\Http\Middleware\VerifyTelegramSecretToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Sentry\Laravel\Integration;
use Spatie\Permission\Middleware\RoleMiddleware;
use Square1\LaravelIdempotency\Http\Middleware\IdempotencyMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(prepend: [
            EnsureMarketingSurfaceOnly::class,
        ]);
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'idempotency' => IdempotencyMiddleware::class,
            'idempotency_for_app' => IdempotencyForAppMiddleware::class,
            'role' => RoleMiddleware::class,
            'banned' => Banned::class,
            'payment.domain' => EnsurePaymentDomain::class,
            'marketing.surface' => EnsureMarketingSurfaceOnly::class,
            'backoffice.domain' => EnsureBackofficeDomain::class,
            'api-access-token' => ApiAccessToken::class,
            'integration-infrastructure-api-access-token' => IntegrationInfrastructureApiAccessToken::class,
            'api-bot-access-token' => ApiBotAccessToken::class,
            'api-deposits-access-token' => ApiDepositsAccessToken::class,
            'api-withdrawals-access-token' => ApiWithdrawalsAccessToken::class,
            'device-access-token' => DeviceAccessToken::class,
            '2fa' => Google2FAMiddleware::class,
            'telegram.secret' => VerifyTelegramSecretToken::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'telegram/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);
    })
    ->withEvents(discover: [
        __DIR__.'/../app/Listeners',
    ])
    ->create();
