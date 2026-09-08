<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\ApiDepositsAccessToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiDepositsAccessTokenTest extends TestCase
{
    public function test_it_rejects_a_request_when_the_configured_token_is_missing(): void
    {
        config()->set('api.api_deposit_token', null);

        $response = (new ApiDepositsAccessToken())->handle(
            Request::create('/api/deposit/webhook', 'POST'),
            fn (): Response => new Response(status: 204),
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
    }
}
