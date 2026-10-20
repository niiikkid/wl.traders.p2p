<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\ApiWithdrawalsAccessToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiWithdrawalsAccessTokenTest extends TestCase
{
    public function test_it_rejects_a_request_when_the_configured_token_is_missing(): void
    {
        config()->set('api.api_withdraw_token', null);

        $response = (new ApiWithdrawalsAccessToken())->handle(
            Request::create('/api/withdraw/webhook', 'POST'),
            fn (): Response => new Response(status: 204),
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
    }
}
