<?php

namespace App\Services\External;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class InvoiceApiClient
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected ?int $merchantId;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.deposit_provider.base_url'), '/');
        $this->apiKey = config('services.deposit_provider.api_key');
        $this->merchantId = $this->toIntOrNull(config('services.deposit_provider.merchant_id'));
    }

    protected function withAuth()
    {
        return Http::withHeaders([
            'X-Api-Key' => $this->apiKey,
        ])->acceptJson();
    }

    public function createInvoice(
        string $currency,
        string $network,
        string $amount,
        ?string $externalInvoiceId = null,
        ?string $callbackUrl = null,
        ?string $tag = null,
        ?array $metadata = null,
        ?string $productName = null,
        ?string $productDescription = null,
        ?string $clientId = null,
    ): array
    {
        $this->ensureConfigured();

        $payload = [
            'currency' => strtolower($currency),
            'network' => strtolower($network),
            'amount' => (string) $amount,
            'merchant_id' => $this->merchantId,
        ];

        if ($externalInvoiceId !== null) {
            $payload['external_invoice_id'] = $externalInvoiceId;
        }
        if ($callbackUrl !== null) {
            $payload['callback_url'] = $callbackUrl;
        }
        if ($tag !== null) {
            $payload['tag'] = $tag;
        }
        if ($metadata !== null) {
            $payload['metadata'] = $metadata;
        }
        if ($productName !== null) {
            $payload['product_name'] = $productName;
        }
        if ($productDescription !== null) {
            $payload['product_description'] = $productDescription;
        }
        if ($clientId !== null) {
            $payload['client_id'] = $clientId;
        }

        $response = $this->withAuth()->post($this->baseUrl . '/invoices', $payload);
        $this->throwIfInvalid($response);
        return $response->json();
    }

    protected function ensureConfigured(): void
    {
        if (! $this->baseUrl || ! $this->apiKey || ! $this->merchantId) {
            abort(500, 'Deposit provider is not configured');
        }
    }

    protected function toIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function getInvoice(string $id): array
    {
        $response = $this->withAuth()->get($this->baseUrl . '/invoices/' . $id);
        $this->throwIfInvalid($response);
        return $response->json();
    }

    public function cancelInvoice(string $id): array
    {
        $response = $this->withAuth()->post($this->baseUrl . '/invoices/' . $id . '/cancel');
        $this->throwIfInvalid($response);
        return $response->json();
    }

    protected function throwIfInvalid(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('message') ?? $response->body();
        abort($response->status(), $message ?: 'External invoice API error');
    }
}


