<?php

declare(strict_types=1);

namespace App\Services\Cascade\Providers;

use App\Models\CascadeDeal;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ExampleCascadeProvider extends AbstractCascadeProvider
{
    protected array $config;
    protected string $code;

    public function __construct(string $code, array $config = [])
    {
        $this->code = $code;
        $this->config = $config;
    }

    public function createDeal(CascadeDeal $cascadeDeal): array
    {
        $this->ensureConfigured();

        $payload = [
            'external_id' => $cascadeDeal->uuid,
            'amount' => (int) $cascadeDeal->amount->getAmount(),
            'currency' => $this->resolveCurrency($cascadeDeal),
            'payment_method' => $this->mapPaymentMethod($cascadeDeal),
            'client_id' => $cascadeDeal->merchantClient?->external_id,
        ];

        $callbackUrl = $this->configValue('callback_url');
        if ($callbackUrl) {
            $payload['callback_url'] = $callbackUrl;
        }

        $payload['merchant_id'] = (string) $this->configValue('merchant_id');

        $response = $this->request()->post($this->buildUrl('/api/h2h/order'), $payload);
        $this->throwIfInvalid($response);

        $data = $response->json();

        return [
            'provider_deal_id' => Arr::get($data, 'data.order_id') ?? Arr::get($data, 'order_id'),
            'status' => Arr::get($data, 'data.status') ?? Arr::get($data, 'status'),
            'amount' => (int) $cascadeDeal->amount->getAmount(),
            'currency' => $cascadeDeal->currency->getCode(),
            'gateway' => [
                'code' => Arr::get($data, 'data.payment_gateway') ?? Arr::get($data, 'payment_gateway'),
                'name' => Arr::get($data, 'data.payment_gateway_name') ?? Arr::get($data, 'payment_gateway_name'),
                'logo_link' => null,
            ],
            'details' => [
                'type' => Arr::get($data, 'data.payment_detail.detail_type')
                    ?? Arr::get($data, 'payment_detail.detail_type'),
                'value' => Arr::get($data, 'data.payment_detail.detail')
                    ?? Arr::get($data, 'payment_detail.detail'),
                'initials' => Arr::get($data, 'data.payment_detail.initials')
                    ?? Arr::get($data, 'payment_detail.initials'),
            ],
            'created_at' => Arr::get($data, 'data.created_at') ?? Arr::get($data, 'created_at'),
            'expires_at' => Arr::get($data, 'data.expires_at') ?? Arr::get($data, 'expires_at'),
            'raw' => $data,
        ];
    }

    public function cancelDeal(CascadeDeal $cascadeDeal, string $providerDealId): array
    {
        $this->ensureConfigured();

        $response = $this->request()->patch(
            $this->buildUrl('/api/h2h/order/' . $providerDealId . '/cancel')
        );
        $this->throwIfInvalid($response);

        return [
            'success' => true,
            'provider_deal_id' => $providerDealId,
            'status' => Arr::get($response->json(), 'data.status')
                ?? Arr::get($response->json(), 'status'),
            'raw' => $response->json(),
        ];
    }

    public function getDeal(CascadeDeal $cascadeDeal, string $providerDealId): array
    {
        $this->ensureConfigured();

        $response = $this->request()->get(
            $this->buildUrl('/api/h2h/order/' . $providerDealId)
        );
        $this->throwIfInvalid($response);

        $data = $response->json();

        return [
            'provider_deal_id' => $providerDealId,
            'status' => Arr::get($data, 'data.status') ?? Arr::get($data, 'status'),
            'amount' => (int) $cascadeDeal->amount->getAmount(),
            'currency' => $cascadeDeal->currency->getCode(),
            'paid_at' => Arr::get($data, 'data.finished_at') ?? Arr::get($data, 'finished_at'),
            'expires_at' => Arr::get($data, 'data.expires_at') ?? Arr::get($data, 'expires_at'),
            'raw' => $data,
        ];
    }

    public function openDispute(CascadeDeal $cascadeDeal, string $providerDealId, array $data = []): array
    {
        $this->ensureConfigured();

        $payload = [
            'receipts' => $data['receipts'] ?? [],
        ];

        $response = $this->request()->post(
            $this->buildUrl('/api/h2h/order/' . $providerDealId . '/dispute'),
            $payload
        );
        $this->throwIfInvalid($response);

        return [
            'dispute_id' => Arr::get($response->json(), 'data.dispute_id')
                ?? Arr::get($response->json(), 'dispute_id')
                ?? (string) Str::uuid(),
            'provider_deal_id' => $providerDealId,
            'status' => Arr::get($response->json(), 'data.status')
                ?? Arr::get($response->json(), 'status'),
            'raw' => $response->json(),
        ];
    }

    public function getDispute(CascadeDeal $cascadeDeal, string $providerDealId, string $disputeId): array
    {
        $this->ensureConfigured();

        $response = $this->request()->get(
            $this->buildUrl('/api/h2h/order/' . $providerDealId . '/dispute')
        );
        $this->throwIfInvalid($response);

        return [
            'dispute_id' => $disputeId,
            'provider_deal_id' => $providerDealId,
            'status' => Arr::get($response->json(), 'data.status')
                ?? Arr::get($response->json(), 'status'),
            'raw' => $response->json(),
        ];
    }

    public function handleCallback(array $payload): array
    {
        return [
            'provider_deal_id' => $payload['order_id'] ?? $payload['data']['order_id'] ?? null,
            'status' => $payload['status'] ?? $payload['data']['status'] ?? 'unknown',
            'event' => $payload['event'] ?? 'status_update',
            'data' => $payload,
        ];
    }

    public function getCode(): string
    {
        return $this->code;
    }

    private function request(): PendingRequest
    {
        $request = Http::acceptJson()
            ->withHeaders([
                'Access-Token' => (string) $this->configValue('access_token'),
            ])
            ->timeout((int) ($this->configValue('timeout') ?? 10));

        if ($this->configValue('verify_ssl') === false) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    private function buildUrl(string $path): string
    {
        return rtrim((string) $this->configValue('base_url'), '/') . $path;
    }

    private function throwIfInvalid(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('message') ?? $response->body();
        throw new RuntimeException($message ?: 'P2PProcessing API error');
    }

    private function ensureConfigured(): void
    {
        foreach (['base_url', 'access_token', 'merchant_id', 'currency_code'] as $key) {
            if (! $this->configValue($key)) {
                throw new RuntimeException('P2PProcessing provider config is incomplete: ' . $key);
            }
        }
    }

    private function configValue(string $key): mixed
    {
        return $this->config[$key] ?? null;
    }

    private function mapPaymentMethod(CascadeDeal $cascadeDeal): string
    {
        return match ($cascadeDeal->payment_method->value) {
            'card' => 'card',
            'sbp' => 'sbp',
            default => $cascadeDeal->payment_method->value,
        };
    }

    private function resolveCurrency(CascadeDeal $cascadeDeal): string
    {
        $currency = strtoupper((string) $this->configValue('currency_code'));
        if ($currency === '') {
            $currency = strtoupper($cascadeDeal->currency->getCode());
        }

        $map = [
            'RUB' => 'RUB',
            'KZT' => 'KZT',
            'UZS' => 'UZS',
        ];

        return $map[$currency] ?? $currency;
    }
}
