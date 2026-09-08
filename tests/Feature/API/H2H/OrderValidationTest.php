<?php

namespace Tests\Feature\API\H2H;

use App\Http\Middleware\ApiAccessToken;
use App\Models\Merchant;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderValidationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_inactive_payment_gateway_is_rejected_during_validation(): void
    {
        $merchant = Merchant::query()->with('user')->firstOrFail();
        $gateway = PaymentGateway::query()->firstOrFail()->replicate();
        $gateway->forceFill([
            'name' => 'Inactive API test gateway',
            'code' => 'inactive-api-test-'.Str::lower(Str::random(8)),
            'is_active' => false,
        ])->save();

        $response = $this
            ->withoutMiddleware(ApiAccessToken::class)
            ->actingAs($merchant->user)
            ->postJson('/api/h2h/order', [
                'merchant_id' => $merchant->uuid,
                'external_id' => 'inactive-gateway-'.Str::uuid(),
                'amount' => 1000,
                'payment_gateway' => $gateway->code,
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_gateway');
    }
}
