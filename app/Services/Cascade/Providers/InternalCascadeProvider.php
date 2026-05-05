<?php

declare(strict_types=1);

namespace App\Services\Cascade\Providers;

use App\Enums\DetailType;
use App\Enums\MarketEnum;
use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Exceptions\CascadeException;
use App\Http\Requests\API\H2H\Order\StoreRequest as H2HStoreRequest;
use App\Models\CascadeDeal;
use App\Models\CascadeProvider;
use App\Models\Order;
use App\Models\OrderManualControlConfirmationCode;
use App\Services\Cascade\CascadeProviderOperationLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Внутренний провайдер каскада (работа с нашими трейдерами)
 */
class InternalCascadeProvider extends AbstractCascadeProvider
{
    public const CODE = 'internal';

    public function __construct(
        string $code,
        array $config = [],
        ?CascadeProvider $providerModel = null,
        ?CascadeProviderOperationLogger $operationLogger = null,
    ) {
        parent::__construct($code, $config, $providerModel, $operationLogger);
    }

    public function createDeal(CascadeDeal $cascadeDeal, ?int $maxWaitMs = null): array
    {
        $merchant = $cascadeDeal->merchant;

        $payload = [
            'merchant_id' => $merchant->uuid,
            'external_id' => $cascadeDeal->external_id,
            'amount' => $cascadeDeal->initial_amount->toInt(),
            'currency' => $cascadeDeal->currency->getCode(),
            'payment_detail_type' => $cascadeDeal->payment_method->detailType()->value,
            'client_id' => $cascadeDeal->merchantClient?->client_id,
            'callback_url' => $cascadeDeal->callback_url,
        ];

        if ($cascadeDeal->market?->equals(MarketEnum::MERCHANT_API) && $cascadeDeal->conversion_price !== null) {
            $payload['rate'] = $cascadeDeal->conversion_price->toPrecision();
        }

        if ($cascadeDeal->manual_control !== null) {
            $payload = array_merge($payload, [
                'manual_control_acquiring' => true,
                'payment_detail_type' => DetailType::CARD->value,
                'card_number' => $cascadeDeal->manual_control->cardNumber,
                'expiry_month' => $cascadeDeal->manual_control->expiryMonth,
                'expiry_year' => $cascadeDeal->manual_control->expiryYear,
                'cvc' => $cascadeDeal->manual_control->cvc,
                'cardholder_name' => $cascadeDeal->manual_control->cardholderName,
            ]);
        }

        $url = $this->providerApiLogUrl('createDeal', $cascadeDeal);
        $startedAt = microtime(true);
        $response_data = null;
        $statusCode = null;

        try {
            $request = H2HStoreRequest::create('/', 'POST', $payload);
            $request->setContainer(app())->setRedirector(app('redirect'));
            if ($maxWaitMs !== null) {
                $request->headers->set('X-Max-Wait-Ms', (string) $maxWaitMs);
            }
            $request->validateResolved();

            $response = services()->orderPooling()->processOrderPooling($request);
            $response_data = json_decode($response->getContent(), true);
            $response_data = is_array($response_data) ? $response_data : ['body' => $response->getContent()];
            $statusCode = $response->getStatusCode();
        } catch (Throwable $e) {
            $this->recordProviderOperation(
                cascadeDeal: $cascadeDeal,
                operation: 'createDeal',
                method: 'POST',
                url: $url,
                requestPayload: $payload,
                responsePayload: $response_data,
                statusCode: $statusCode,
                startedAt: $startedAt,
                isSuccessful: false,
                exception: $e,
            );

            throw $e instanceof CascadeException
                ? $e
                : CascadeException::make($e->getMessage());
        }

        if (! ($response_data['success'] ?? false)) {
            $exception = CascadeException::make($response_data['message'] ?? 'Не удалось создать сделку у внутреннего провайдера.');

            $this->recordProviderOperation(
                cascadeDeal: $cascadeDeal,
                operation: 'createDeal',
                method: 'POST',
                url: $url,
                requestPayload: $payload,
                responsePayload: $response_data,
                statusCode: $statusCode,
                startedAt: $startedAt,
                isSuccessful: false,
                exception: $exception,
            );

            throw $exception;
        }

        $order_data = $response_data['data'] ?? $response_data;

        $normalizedResponse = [
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

        $this->recordProviderOperation(
            cascadeDeal: $cascadeDeal,
            operation: 'createDeal',
            method: 'POST',
            url: $url,
            requestPayload: $payload,
            responsePayload: $response_data,
            statusCode: $statusCode,
            startedAt: $startedAt,
            isSuccessful: true,
            context: ['provider_deal_id' => (string) Arr::get($normalizedResponse, 'provider_deal_id')],
        );

        return $normalizedResponse;
    }

    public function cancelDeal(CascadeDeal $cascadeDeal, string $providerDealId): array
    {
        $url = $this->providerApiLogUrl('cancelDeal', $cascadeDeal, ['provider_deal_id' => $providerDealId]);
        $payload = ['provider_deal_id' => $providerDealId];
        $startedAt = microtime(true);
        $responsePayload = null;

        try {
            $order = $this->resolveOrder($cascadeDeal, $providerDealId);
            $responsePayload = $this->cancelOrder($order);

            $this->recordProviderOperation(
                cascadeDeal: $cascadeDeal,
                operation: 'cancelDeal',
                method: 'PATCH',
                url: $url,
                requestPayload: $payload,
                responsePayload: $responsePayload['raw'] ?? $responsePayload,
                statusCode: 200,
                startedAt: $startedAt,
                isSuccessful: true,
                context: $payload,
            );

            return $responsePayload;
        } catch (Throwable $e) {
            $this->recordProviderOperation(
                cascadeDeal: $cascadeDeal,
                operation: 'cancelDeal',
                method: 'PATCH',
                url: $url,
                requestPayload: $payload,
                responsePayload: $responsePayload,
                statusCode: null,
                startedAt: $startedAt,
                isSuccessful: false,
                exception: $e,
                context: $payload,
            );

            throw $e instanceof CascadeException
                ? $e
                : CascadeException::make($e->getMessage());
        }
    }

    public function getDeal(CascadeDeal $cascadeDeal, string $providerDealId): array
    {
        $url = $this->providerApiLogUrl('getDeal', $cascadeDeal, ['provider_deal_id' => $providerDealId]);
        $payload = ['provider_deal_id' => $providerDealId];
        $startedAt = microtime(true);

        try {
            $order = $this->resolveOrder($cascadeDeal, $providerDealId);

            $responsePayload = [
                'order_id' => $order->uuid,
                'status' => $order->status->value,
                'sub_status' => $order->sub_status?->value,
            ];

            $normalizedResponse = [
                'provider_deal_id' => $order->uuid,
                'status' => $order->status->value,
                'sub_status' => $order->sub_status?->value,
                'gateway' => [
                    'code' => $order->paymentGateway?->code,
                    'name' => $order->paymentGateway?->name,
                    'logo_link' => null,
                ],
                'details' => [
                    'type' => $order->manual_control_acquiring ? null : $order->paymentDetail?->detail_type,
                    'value' => $order->manual_control_acquiring ? null : $order->paymentDetail?->detail,
                    'initials' => $order->manual_control_acquiring ? null : $order->paymentDetail?->initials,
                ],
                'finished_at' => $order->finished_at?->getTimestamp(),
                'raw' => $responsePayload,
            ];

            $this->recordProviderOperation(
                cascadeDeal: $cascadeDeal,
                operation: 'getDeal',
                method: 'GET',
                url: $url,
                requestPayload: $payload,
                responsePayload: $responsePayload,
                statusCode: 200,
                startedAt: $startedAt,
                isSuccessful: true,
                context: $payload,
            );

            return $normalizedResponse;
        } catch (Throwable $e) {
            $this->recordProviderOperation(
                cascadeDeal: $cascadeDeal,
                operation: 'getDeal',
                method: 'GET',
                url: $url,
                requestPayload: $payload,
                responsePayload: null,
                statusCode: null,
                startedAt: $startedAt,
                isSuccessful: false,
                exception: $e,
                context: $payload,
            );

            throw $e instanceof CascadeException
                ? $e
                : CascadeException::make($e->getMessage());
        }
    }

    public function storeConfirmationCode(CascadeDeal $cascadeDeal, string $confirmationCode): array
    {
        $url = $this->providerApiLogUrl('storeConfirmationCode', $cascadeDeal);
        $payload = ['confirmation_code' => $confirmationCode];
        $startedAt = microtime(true);
        $responsePayload = null;

        try {
            $order = $this->resolveCascadeOrder($cascadeDeal);

            if (! $order->manual_control_acquiring) {
                throw CascadeException::make('Эндпоинт доступен только для сделок в режиме Manual Control Acquiring.');
            }

            if ($order->status->notEquals(OrderStatus::PENDING)) {
                throw CascadeException::make('Нельзя отправить код для завершенной сделки.');
            }

            $created_code = OrderManualControlConfirmationCode::query()->create([
                'order_id' => $order->id,
                'confirmation_code' => $confirmationCode,
            ]);

            $responsePayload = [
                'order_id' => $order->uuid,
                'confirmation_code' => [
                    'value' => $created_code->confirmation_code,
                    'created_at' => $created_code->created_at?->getTimestamp(),
                ],
            ];

            $this->recordProviderOperation(
                cascadeDeal: $cascadeDeal,
                operation: 'storeConfirmationCode',
                method: 'POST',
                url: $url,
                requestPayload: $payload,
                responsePayload: $responsePayload,
                statusCode: 200,
                startedAt: $startedAt,
                isSuccessful: true,
                context: ['provider_deal_id' => $order->uuid],
            );

            return $responsePayload;
        } catch (Throwable $e) {
            $this->recordProviderOperation(
                cascadeDeal: $cascadeDeal,
                operation: 'storeConfirmationCode',
                method: 'POST',
                url: $url,
                requestPayload: $payload,
                responsePayload: $responsePayload,
                statusCode: null,
                startedAt: $startedAt,
                isSuccessful: false,
                exception: $e,
            );

            throw $e instanceof CascadeException
                ? $e
                : CascadeException::make($e->getMessage());
        }
    }

    public function openDispute(CascadeDeal $cascadeDeal, string $providerDealId, array $data = []): array
    {
        $url = $this->providerApiLogUrl('openDispute', $cascadeDeal, ['provider_deal_id' => $providerDealId]);
        $payload = $this->disputeRequestPayload($providerDealId, $data);
        $startedAt = microtime(true);
        $responsePayload = null;

        try {
            $order = $this->resolveOrder($cascadeDeal, $providerDealId);

            if ($order->dispute) {
                throw CascadeException::make('Dispute already exists.');
            }

            /** @var UploadedFile|null $firstReceipt */
            $firstReceipt = Arr::get($data, 'receipts.0');
            $dispute = services()->dispute()->create($order->id, $firstReceipt);

            $responsePayload = [
                'order_id' => $order->uuid,
                'status' => $dispute->status->value,
                'cancel_reason' => $dispute->reason,
            ];

            $normalizedResponse = [
                'dispute_id' => (string) $dispute->id,
                'provider_deal_id' => $order->uuid,
                'status' => $dispute->status->value,
                'cancel_reason' => $dispute->reason,
                'raw' => $responsePayload,
            ];

            $this->recordProviderOperation(
                cascadeDeal: $cascadeDeal,
                operation: 'openDispute',
                method: 'POST',
                url: $url,
                requestPayload: $payload,
                responsePayload: $responsePayload,
                statusCode: 200,
                startedAt: $startedAt,
                isSuccessful: true,
                context: ['provider_deal_id' => $providerDealId],
            );

            return $normalizedResponse;
        } catch (Throwable $e) {
            $this->recordProviderOperation(
                cascadeDeal: $cascadeDeal,
                operation: 'openDispute',
                method: 'POST',
                url: $url,
                requestPayload: $payload,
                responsePayload: $responsePayload,
                statusCode: null,
                startedAt: $startedAt,
                isSuccessful: false,
                exception: $e,
                context: ['provider_deal_id' => $providerDealId],
            );

            throw $e instanceof CascadeException
                ? $e
                : CascadeException::make($e->getMessage());
        }
    }

    public function handleCallback(array $payload): array
    {
        $dispute = Arr::get($payload, 'dispute');

        return [
            'provider_deal_id' => Arr::get($payload, 'order_id'),
            'cascade_deal_uuid' => Arr::get($payload, 'cascade_deal_uuid'),
            'status' => Arr::get($payload, 'status', 'pending'),
            'sub_status' => Arr::get($payload, 'sub_status'),
            'amount' => Arr::get($payload, 'amount'),
            'initial_amount' => Arr::get($payload, 'initial_amount'),
            'currency' => Arr::get($payload, 'currency'),
            'debit' => Arr::get($payload, 'debit'),
            'credit' => Arr::get($payload, 'credit'),
            'service_profit' => Arr::get($payload, 'service_profit'),
            'usdt_amount' => Arr::get($payload, 'usdt_amount'),
            'market' => Arr::get($payload, 'market'),
            'conversion_price' => Arr::get($payload, 'conversion_price'),
            'rate_fixed_at' => Arr::get($payload, 'rate_fixed_at'),
            'confirmation_type' => Arr::get($payload, 'manual_control_confirmation_type'),
            'reject_reason' => Arr::get($payload, 'manual_control_reject_reason'),
            'dispute' => is_array($dispute) ? $dispute : null,
            'gateway' => Arr::get($payload, 'gateway'),
            'details' => Arr::get($payload, 'details'),
            'created_at' => Arr::get($payload, 'created_at'),
            'finished_at' => Arr::get($payload, 'finished_at'),
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
        return match ($operation) {
            'createDeal' => 'internal://services.orderPooling.processOrderPooling',
            'cancelDeal' => 'internal://services.order.finishOrderAsFailed',
            'getDeal' => 'internal://order.resolveOrder',
            'storeConfirmationCode' => 'internal://orderManualControlConfirmationCode.create',
            'openDispute' => 'internal://services.dispute.create',
            'cancelDispute' => 'internal://services.dispute.cancel',
            'callback' => 'internal://provider.callback.handle',
            default => 'internal://provider.operation.'.$operation,
        };
    }

    private function resolveCascadeOrder(CascadeDeal $cascadeDeal): Order
    {
        $order = $cascadeDeal->order;

        if (! $order instanceof Order) {
            throw CascadeException::make('Внутренняя сделка для каскадной сделки не найдена.');
        }

        return $order;
    }

    private function resolveOrder(CascadeDeal $cascadeDeal, string $providerDealId): Order
    {
        $query = Order::withoutGlobalScopes()
            ->where('uuid', $providerDealId);

        $order = $cascadeDeal->order_id !== null
            ? (clone $query)->whereKey($cascadeDeal->order_id)->first()
            : $query
                ->where('merchant_id', $cascadeDeal->merchant_id)
                ->where('external_id', $cascadeDeal->external_id)
                ->first();

        if (! $order instanceof Order) {
            throw CascadeException::make('Внутренняя сделка для каскадной сделки не найдена.');
        }

        return $order;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function disputeRequestPayload(string $providerDealId, array $data): array
    {
        $payload = [
            'provider_deal_id' => $providerDealId,
            ...Arr::except($data, ['receipts']),
        ];

        $receipts = collect((array) ($data['receipts'] ?? []))
            ->values()
            ->map(function (mixed $receipt, int $index): array {
                if (! $receipt instanceof UploadedFile) {
                    return [
                        'index' => $index,
                        'type' => get_debug_type($receipt),
                    ];
                }

                return [
                    'index' => $index,
                    'original_name' => $receipt->getClientOriginalName() ?: null,
                    'mime_type' => $receipt->getMimeType(),
                    'size' => $receipt->getSize(),
                    'extension' => $receipt->extension(),
                ];
            })
            ->all();

        if ($receipts !== []) {
            $payload['receipts'] = $receipts;
        }

        return $payload;
    }

    private function cancelOrder(Order $order): array
    {
        try {
            if ($order->status->notEquals(OrderStatus::PENDING)) {
                throw CascadeException::make('It is not possible to cancel a completed order.');
            }

            services()->order()->finishOrderAsFailed($order->id, OrderSubStatus::CANCELED);

            $order->refresh();
            $order->load('paymentGateway', 'paymentDetail');

            return [
                'provider_deal_id' => $order->uuid,
                'status' => $order->status->value,
                'sub_status' => $order->sub_status?->value,
                'finished_at' => $order->finished_at?->getTimestamp(),
                'raw' => [
                    'order_id' => $order->uuid,
                    'status' => $order->status->value,
                    'sub_status' => $order->sub_status?->value,
                ],
            ];
        } catch (Throwable $e) {
            throw $e instanceof CascadeException
                ? $e
                : CascadeException::make($e->getMessage());
        }
    }
}
