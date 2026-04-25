<?php

declare(strict_types=1);

namespace App\Services\Cascade\Providers;

use App\Http\Requests\API\H2H\Order\StoreRequest as H2HStoreRequest;
use App\Models\CascadeDeal;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * Внутренний провайдер каскада (работа с нашими трейдерами)
 */
class InternalCascadeProvider extends AbstractCascadeProvider
{
    public const CODE = 'internal';

    protected array $config;
    protected string $code;

    public function __construct(string $code, array $config = [])
    {
        $this->code = $code;
        $this->config = $config;
    }

    public function createDeal(CascadeDeal $cascadeDeal): array
    {
        $merchant = $cascadeDeal->merchant;

        $payload = [
            'merchant_id' => $merchant->uuid,
            'external_id' => $cascadeDeal->external_id,
            'amount' => (int) $cascadeDeal->amount->getAmount(),
            'currency' => $cascadeDeal->currency->getCode(),
            'client_id' => $cascadeDeal->merchantClient?->client_id,
            'callback_url' => $cascadeDeal->callback_url,
        ];

        $request = H2HStoreRequest::create('/', 'POST', $payload);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();

        $response = services()->orderPooling()->processOrderPooling($request);
        $response_data = json_decode($response->getContent(), true);

        if (! ($response_data['success'] ?? false)) {
            throw new RuntimeException($response_data['message'] ?? 'Не удалось создать сделку у внутреннего провайдера.');
        }

        $order_data = $response_data['data'] ?? $response_data;

        return [
            'provider_deal_id' => Arr::get($order_data, 'order_id'),
            'status' => Arr::get($order_data, 'status'),
            'amount' => Arr::get($order_data, 'amount'),
            'currency' => Arr::get($order_data, 'currency'),
            'gateway' => [
                'code' => Arr::get($order_data, 'payment_gateway'),
                'name' => Arr::get($order_data, 'payment_gateway_name'),
                'logo_link' => null,
            ],
            'details' => [
                'type' => Arr::get($order_data, 'payment_detail.detail_type'),
                'value' => Arr::get($order_data, 'payment_detail.detail'),
                'initials' => Arr::get($order_data, 'payment_detail.initials'),
            ],
            'created_at' => Arr::get($order_data, 'created_at'),
            'expires_at' => Arr::get($order_data, 'expires_at'),
            'raw' => $response_data,
        ];
    }

    public function cancelDeal(CascadeDeal $cascadeDeal, string $providerDealId): array
    {
        // TODO: Реализовать отмену внутренней сделки
        return [
            'status' => 'not_implemented',
            'provider_deal_id' => $providerDealId,
        ];
    }

    public function getDeal(CascadeDeal $cascadeDeal, string $providerDealId): array
    {
        // TODO: Реализовать получение состояния внутренней сделки
        return [
            'status' => 'not_implemented',
            'provider_deal_id' => $providerDealId,
        ];
    }

    public function openDispute(CascadeDeal $cascadeDeal, string $providerDealId, array $data = []): array
    {
        // TODO: Реализовать открытие спора по внутренней сделке
        return [
            'status' => 'not_implemented',
            'provider_deal_id' => $providerDealId,
        ];
    }

    public function getDispute(CascadeDeal $cascadeDeal, string $providerDealId, string $disputeId): array
    {
        // TODO: Реализовать получение спора по внутренней сделке
        return [
            'status' => 'not_implemented',
            'provider_deal_id' => $providerDealId,
            'dispute_id' => $disputeId,
        ];
    }

    public function getCode(): string
    {
        return $this->code;
    }
}
