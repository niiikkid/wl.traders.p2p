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
use App\Models\Order;
use App\Models\OrderManualControlConfirmationCode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Throwable;

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

        try {
            $request = H2HStoreRequest::create('/', 'POST', $payload);
            $request->setContainer(app())->setRedirector(app('redirect'));
            if ($maxWaitMs !== null) {
                $request->headers->set('X-Max-Wait-Ms', (string) $maxWaitMs);
            }
            $request->validateResolved();

            $response = services()->orderPooling()->processOrderPooling($request);
            $response_data = json_decode($response->getContent(), true);
        } catch (Throwable $e) {
            throw $e instanceof CascadeException
                ? $e
                : CascadeException::make($e->getMessage());
        }

        if (! ($response_data['success'] ?? false)) {
            throw CascadeException::make($response_data['message'] ?? 'Не удалось создать сделку у внутреннего провайдера.');
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
        $order = $this->resolveOrder($cascadeDeal, $providerDealId);

        return $this->cancelOrder($order);
    }

    public function getDeal(CascadeDeal $cascadeDeal, string $providerDealId): array
    {
        $order = $this->resolveOrder($cascadeDeal, $providerDealId);

        return [
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
            'raw' => [
                'order_id' => $order->uuid,
                'status' => $order->status->value,
                'sub_status' => $order->sub_status?->value,
            ],
        ];
    }

    public function storeConfirmationCode(CascadeDeal $cascadeDeal, string $confirmationCode): array
    {
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

        return [
            'order_id' => $order->uuid,
            'confirmation_code' => [
                'value' => $created_code->confirmation_code,
                'created_at' => $created_code->created_at?->getTimestamp(),
            ],
        ];
    }

    public function openDispute(CascadeDeal $cascadeDeal, string $providerDealId, array $data = []): array
    {
        $order = $this->resolveOrder($cascadeDeal, $providerDealId);

        if ($order->dispute) {
            throw CascadeException::make('Dispute already exists.');
        }

        /** @var UploadedFile|null $firstReceipt */
        $firstReceipt = Arr::get($data, 'receipts.0');
        $dispute = services()->dispute()->create($order->id, $firstReceipt);

        return [
            'dispute_id' => (string) $dispute->id,
            'provider_deal_id' => $order->uuid,
            'status' => $dispute->status->value,
            'cancel_reason' => $dispute->reason,
            'raw' => [
                'order_id' => $order->uuid,
                'status' => $dispute->status->value,
                'cancel_reason' => $dispute->reason,
            ],
        ];
    }

    public function getDispute(CascadeDeal $cascadeDeal, string $providerDealId, string $disputeId): array
    {
        $order = $this->resolveOrder($cascadeDeal, $providerDealId);
        $dispute = $order->dispute;

        if (! $dispute) {
            throw CascadeException::make('По сделке пока что небыло споров.');
        }

        return [
            'dispute_id' => (string) $dispute->id,
            'provider_deal_id' => $order->uuid,
            'status' => $dispute->status->value,
            'cancel_reason' => $dispute->reason,
            'raw' => [
                'order_id' => $order->uuid,
                'status' => $dispute->status->value,
                'cancel_reason' => $dispute->reason,
            ],
        ];
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
            'getDispute' => 'internal://order.dispute.get',
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
