<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\IntegrationInfrastructureApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IntegrationInfrastructureApiAccessToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $incomingToken = (string) $request->header('Access-Token', '');

        if ($incomingToken === '' || ! hash_equals(IntegrationInfrastructureApiToken::get(), $incomingToken)) {
            return response()->json([
                'message' => 'Invalid Access-Token.',
            ], 401);
        }

        return $next($request);
    }
}
