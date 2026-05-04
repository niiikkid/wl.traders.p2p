<?php

declare(strict_types=1);

namespace App\Services\Cascade\Providers;

use App\Enums\MarketEnum;
use App\Models\CascadeDeal;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SelfTestCascadeProvider extends AbstractCascadeProvider
{
    public const CODE = 'self-test';

    public const SUPPORTS_CALLBACK_ENDPOINT = true;

    protected array $config;

    protected string $code;

    public function __construct(string $code, array $config = [])
    {
        $this->code = $code;
        $this->config = $config;
    }

    public function createDeal(CascadeDeal $cascadeDeal, ?int $maxWaitMs = null): array
    {
        $payload = [
            'merchant_id' => $cascadeDeal->merchant->uuid,
            'external_id' => $this->externalId($cascadeDeal),
            'amount' => $cascadeDeal->initial_amount->toInt(),
            'currency' => $cascadeDeal->currency->getCode(),
            'payment_detail_type' => $cascadeDeal->payment_method->detailType()->value,
            'client_id' => $cascadeDeal->merchantClient?->client_id,
            'callback_url' => $this->callbackUrl(),
        ];

        if ($cascadeDeal->market?->equals(MarketEnum::MERCHANT_API) && $cascadeDeal->conversion_price !== null) {
            $payload['rate'] = $cascadeDeal->conversion_price->toPrecision();
        }

        if ($cascadeDeal->manual_control !== null) {
            $payload = array_merge($payload, [
                'manual_control_acquiring' => true,
                'payment_detail_type' => 'card',
                'card_number' => $cascadeDeal->manual_control->cardNumber,
                'expiry_month' => $cascadeDeal->manual_control->expiryMonth,
                'expiry_year' => $cascadeDeal->manual_control->expiryYear,
                'cvc' => $cascadeDeal->manual_control->cvc,
                'cardholder_name' => $cascadeDeal->manual_control->cardholderName,
            ]);
        }

        $response = $this->request($cascadeDeal, $maxWaitMs)->post($this->buildUrl('/api/h2h/order'), $payload);
        $this->throwIfInvalid($response);

        return $this->normalizeOrderResponse($response->json());
    }

    public function cancelDeal(CascadeDeal $cascadeDeal, string $providerDealId): array
    {
        $response = $this->request($cascadeDeal)->patch($this->buildUrl('/api/h2h/order/'.$providerDealId.'/cancel'));
        $this->throwIfInvalid($response);

        return $this->normalizeOrderResponse($response->json(), $providerDealId);
    }

    public function getDeal(CascadeDeal $cascadeDeal, string $providerDealId): array
    {
        $response = $this->request($cascadeDeal)->get($this->buildUrl('/api/h2h/order/'.$providerDealId));
        $this->throwIfInvalid($response);

        return $this->normalizeOrderResponse($response->json(), $providerDealId);
    }

    public function storeConfirmationCode(CascadeDeal $cascadeDeal, string $confirmationCode): array
    {
        $providerDealId = (string) $cascadeDeal->selectedTransaction?->provider_deal_id;
        if ($providerDealId === '') {
            throw new RuntimeException('Self-test provider deal id is missing.');
        }

        $response = $this->request($cascadeDeal)->post(
            $this->buildUrl('/api/h2h/order/'.$providerDealId.'/confirmation-code'),
            ['confirmation_code' => $confirmationCode],
        );
        $this->throwIfInvalid($response);

        return $response->json('data') ?? $response->json();
    }

    public function openDispute(CascadeDeal $cascadeDeal, string $providerDealId, array $data = []): array
    {
        $payload = [];
        $firstReceipt = Arr::get($data, 'receipts.0');

        if ($firstReceipt instanceof UploadedFile) {
            $payload['receipt'] = base64_encode((string) file_get_contents($firstReceipt->getPathname()));
        }

        $response = $this->request($cascadeDeal)->post(
            $this->buildUrl('/api/h2h/order/'.$providerDealId.'/dispute'),
            $payload,
        );
        $this->throwIfInvalid($response);

        return $this->normalizeDisputeResponse($response->json(), $providerDealId);
    }

    public function getDispute(CascadeDeal $cascadeDeal, string $providerDealId, string $disputeId): array
    {
        $response = $this->request($cascadeDeal)->get($this->buildUrl('/api/h2h/order/'.$providerDealId.'/dispute'));
        $this->throwIfInvalid($response);

        return $this->normalizeDisputeResponse($response->json(), $providerDealId, $disputeId);
    }

    public function handleCallback(array $payload): array
    {
        return [
            'provider_deal_id' => Arr::get($payload, 'order_id'),
            'cascade_deal_uuid' => $this->cascadeDealUuidFromExternalId((string) Arr::get($payload, 'external_id', '')),
            'status' => Arr::get($payload, 'status', 'unknown'),
            'sub_status' => Arr::get($payload, 'sub_status'),
            'amount' => Arr::get($payload, 'amount'),
            'currency' => Arr::get($payload, 'currency'),
            'confirmation_type' => Arr::get($payload, 'manual_control_confirmation_type'),
            'reject_reason' => Arr::get($payload, 'reject_reason'),
            'event' => 'status_update',
            'data' => $payload,
        ];
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function providerApiLogUrl(string $operation, ?CascadeDeal $cascadeDeal = null, array $context = []): string
    {
        $providerDealId = (string) ($context['provider_deal_id'] ?? $cascadeDeal?->selectedTransaction?->provider_deal_id ?? '');

        return match ($operation) {
            'createDeal' => $this->buildUrl('/api/h2h/order'),
            'cancelDeal' => $this->buildUrl('/api/h2h/order/'.$providerDealId.'/cancel'),
            'getDeal' => $this->buildUrl('/api/h2h/order/'.$providerDealId),
            'storeConfirmationCode' => $this->buildUrl('/api/h2h/order/'.$providerDealId.'/confirmation-code'),
            'openDispute' => $this->buildUrl('/api/h2h/order/'.$providerDealId.'/dispute'),
            'getDispute' => $this->buildUrl('/api/h2h/order/'.$providerDealId.'/dispute'),
            default => $this->buildUrl('/api/h2h/order'),
        };
    }

    private function normalizeOrderResponse(array $response, ?string $fallbackProviderDealId = null): array
    {
        $data = $response['data'] ?? $response;

        return [
            'provider_deal_id' => Arr::get($data, 'order_id', $fallbackProviderDealId),
            'status' => Arr::get($data, 'status'),
            'sub_status' => Arr::get($data, 'sub_status'),
            'amount' => Arr::get($data, 'amount'),
            'currency' => Arr::get($data, 'currency'),
            'settlement' => [
                'provider_fee_percent' => null,
                'provider_fee_amount' => Arr::get($data, 'merchant_profit'),
                'provider_receivable_amount' => Arr::get($data, 'amount'),
                'currency' => Arr::get($data, 'currency'),
            ],
            'gateway' => [
                'code' => Arr::get($data, 'payment_gateway'),
                'name' => Arr::get($data, 'payment_gateway_name'),
                'logo_link' => null,
            ],
            'details' => [
                'type' => Arr::get($data, 'payment_detail.detail_type'),
                'value' => Arr::get($data, 'payment_detail.detail'),
                'initials' => Arr::get($data, 'payment_detail.initials'),
            ],
            'created_at' => Arr::get($data, 'created_at'),
            'expires_at' => Arr::get($data, 'expires_at'),
            'finished_at' => Arr::get($data, 'finished_at'),
            'raw' => $response,
        ];
    }

    private function normalizeDisputeResponse(array $response, string $providerDealId, ?string $fallbackDisputeId = null): array
    {
        $data = $response['data'] ?? $response;
        $dispute = Arr::get($data, 'payment_detail.dispute', $data);

        return [
            'dispute_id' => Arr::get($dispute, 'dispute_id', $fallbackDisputeId),
            'provider_deal_id' => Arr::get($data, 'order_id', $providerDealId),
            'status' => Arr::get($dispute, 'status'),
            'cancel_reason' => Arr::get($dispute, 'cancel_reason'),
            'raw' => $response,
        ];
    }

    private function request(CascadeDeal $cascadeDeal, ?int $maxWaitMs = null): PendingRequest
    {
        $headers = [
            'Access-Token' => (string) $cascadeDeal->merchant->user->api_access_token,
        ];

        if ($maxWaitMs !== null) {
            $headers['X-Max-Wait-Ms'] = (string) $maxWaitMs;
        }

        $timeout = (int) ($this->configValue('timeout') ?? 10);
        if ($maxWaitMs !== null) {
            $timeout = max(1, min($timeout, (int) ceil($maxWaitMs / 1000)));
        }

        $request = Http::acceptJson()
            ->withHeaders($headers)
            ->timeout($timeout);

        if ($this->configValue('verify_ssl') === false) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    protected function buildUrl(string $path): string
    {
        $raw = $this->configValue('base_url');
        $base = is_string($raw) ? trim($raw) : '';

        return $this->integrationHttpUrl($base, $path);
    }

    private function throwIfInvalid(Response $response): void
    {
        if ($response->successful() && $response->json('success') !== false) {
            return;
        }

        $message = $response->json('message') ?? $response->json('error.message') ?? $response->body();

        throw new RuntimeException($message ?: 'Self-test host API error');
    }

    private function callbackUrl(): string
    {
        $callbackUrl = $this->configValue('callback_url');

        if (is_string($callbackUrl) && trim($callbackUrl) !== '') {
            return trim($callbackUrl);
        }

        return $this->buildUrl('/api/v2/providers/'.$this->getCode().'/callback');
    }

    private function configValue(string $key): mixed
    {
        return $this->config[$key] ?? null;
    }

    private function externalId(CascadeDeal $cascadeDeal): string
    {
        return 'self-test:'.$cascadeDeal->uuid.':'.$cascadeDeal->external_id;
    }

    private function cascadeDealUuidFromExternalId(string $externalId): ?string
    {
        if (! str_starts_with($externalId, 'self-test:')) {
            return null;
        }

        return explode(':', $externalId, 3)[1] ?? null;
    }
}
