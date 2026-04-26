<?php

declare(strict_types=1);

namespace App\Services\Cascade;

use App\Contracts\CascadeServiceContract;
use App\DTO\Cascade\CreateCascadeDealDTO;
use App\Enums\CascadeTransactionStatus;
use App\Enums\MarketEnum;
use App\Enums\OrderStatus;
use App\Enums\OrderSubStatus;
use App\Enums\ProviderType;
use App\Exceptions\CascadeException;
use App\Models\CascadeDeal;
use App\Models\CascadeProvider;
use App\Models\CascadeTransaction;
use App\Models\MerchantClient;
use App\Models\Order;
use App\Models\ValueObjects\CascadeManualControl;
use App\Services\Cascade\Providers\InternalCascadeProvider;
use App\Services\Money\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Центральный сервис каскада.
 *
 * На текущем этапе создаёт каскадную сделку через внутреннего провайдера.
 */
class CascadeService implements CascadeServiceContract
{
    public function createDeal(CreateCascadeDealDTO $dto): CascadeDeal
    {
        $timeout = 30 * 1000;

        $max_wait_ms = $timeout;
        $interval_ms = 100;
        $waited = 0;
        $processing_time_ms = 0;
        $max_wait_processing_ms = 3000;

        $job_id = Str::uuid()->toString();
        cache()->put("cascade:deal:create:$job_id", json_encode([
            'status' => 'processing',
        ]), 60);

        $payload = [
            'merchant_id' => $dto->merchantId,
            'external_id' => $dto->externalId,
            'amount' => $dto->amount,
            'currency' => $dto->currency,
            'payment_method' => $dto->paymentMethod->value,
            'callback_url' => $dto->callbackUrl,
            'client_id' => $dto->clientId,
            'rate' => $dto->rate,
            'manual_control' => CascadeManualControl::make(
                manualControlAcquiring: $dto->manualControlAcquiring,
                cardNumber: $dto->cardNumber,
                expiryMonth: $dto->expiryMonth,
                expiryYear: $dto->expiryYear,
                cvc: $dto->cvc,
                cardholderName: $dto->cardholderName,
            )?->toArray(),
        ];

        try {
            $cascade_deal = $this->createCascadeDeal($dto);

            $this->createInternalProviderDeal($cascade_deal, $payload);

            cache()->put("cascade:deal:create:$job_id", json_encode([
                'status' => 'done',
                'cascade_deal_id' => $cascade_deal->id,
            ]), 60);

            // TODO: Dispatch one attempt job per external provider and let the first successful attempt win.
            // TODO: Add loser cancellation and atomic winner locking when external providers are enabled.
        } catch (CascadeException|Throwable $e) {
            cache()->put("cascade:deal:create:$job_id", json_encode([
                'status' => 'failed',
                'exception' => [
                    'class' => get_class($e),
                    'message' => $e->getMessage(),
                ],
            ]), 60);
        }

        while ($waited < $max_wait_ms) {
            usleep($interval_ms * 1000);
            $waited += $interval_ms;

            $result = cache()->get("cascade:deal:create:$job_id");

            if ($result) {
                $data = json_decode($result, true);

                if (empty($data['status'])) {
                    break;
                }

                if ($data['status'] === 'queued' && $waited > $max_wait_ms + ($interval_ms * 2)) {
                    cache()->put("cascade:deal:create:$job_id", json_encode([
                        'status' => 'expired',
                    ]), 60);
                    break;
                }

                if ($data['status'] === 'done') {
                    $cascade_deal = CascadeDeal::find($data['cascade_deal_id']);

                    if (! $cascade_deal) {
                        throw CascadeException::make('Не удалось получить каскадную сделку.');
                    }

                    return $cascade_deal;
                } elseif ($data['status'] === 'failed') {
                    if (empty($data['exception']['class']) || empty($data['exception']['message'])) {
                        throw CascadeException::make('Произошла неизвестная ошибка при обработке запроса');
                    }

                    if (is_a($data['exception']['class'], CascadeException::class, true)) {
                        throw CascadeException::make($data['exception']['message']);
                    } elseif (is_a($data['exception']['class'], Throwable::class, true)) {
                        throw CascadeException::make('Произошла ошибка при обработке запроса');
                    }

                    throw CascadeException::make('Произошла неизвестная ошибка при обработке запроса');
                } elseif ($data['status'] === 'expired') {
                    break;
                } elseif ($data['status'] === 'processing') {
                    $processing_time_ms = $processing_time_ms + $interval_ms;

                    if ($processing_time_ms > $max_wait_processing_ms) {
                        break;
                    }
                }
            } else {
                break;
            }
        }

        throw CascadeException::make('Не удалось обработать запрос вовремя. Повторите попытку позже.');
    }

    private function createCascadeDeal(CreateCascadeDealDTO $dto): CascadeDeal
    {
        $merchant_client_id = null;

        if ($dto->clientId) {
            $merchant_client_id = MerchantClient::query()->firstOrCreate([
                'merchant_id' => $dto->merchantId,
                'client_id' => $dto->clientId,
            ])->id;
        }

        $attributes = [
            'uuid' => (string) Str::uuid(),
            'external_id' => $dto->externalId,
            'merchant_id' => $dto->merchantId,
            'merchant_client_id' => $merchant_client_id,
            'amount' => $dto->amount,
            'initial_amount' => $dto->amount,
            'currency' => $dto->currency,
            'status' => OrderStatus::PENDING,
            'sub_status' => OrderSubStatus::WAITING_FOR_DETAILS_TO_BE_SELECTED,
            'payment_method' => $dto->paymentMethod,
            'manual_control' => CascadeManualControl::make(
                manualControlAcquiring: $dto->manualControlAcquiring,
                cardNumber: $dto->cardNumber,
                expiryMonth: $dto->expiryMonth,
                expiryYear: $dto->expiryYear,
                cvc: $dto->cvc,
                cardholderName: $dto->cardholderName,
            ),
            'callback_url' => $dto->callbackUrl,
        ];

        if ($dto->rate !== null) {
            $attributes['market'] = MarketEnum::MERCHANT_API;
            $attributes['conversion_price'] = Money::fromPrecision($dto->rate, $dto->currency);
            $attributes['rate_fixed_at'] = now();
        }

        return CascadeDeal::create($attributes);
    }

    private function createInternalProviderDeal(CascadeDeal $cascade_deal, array $payload): void
    {
        $provider_model = CascadeProvider::query()->firstOrCreate(
            ['code' => InternalCascadeProvider::CODE],
            [
                'name' => 'Internal',
                'provider_type' => ProviderType::INTERNAL,
                'is_active' => true,
                'priority' => 0,
                'description' => 'Internal liquidity provider.',
            ],
        );

        $provider = new InternalCascadeProvider(InternalCascadeProvider::CODE);

        try {
            $response_payload = $provider->createDeal($cascade_deal->loadMissing(['merchant', 'merchantClient']));
        } catch (Throwable $e) {
            CascadeTransaction::create([
                'cascade_deal_id' => $cascade_deal->id,
                'provider_id' => $provider_model->id,
                'status' => CascadeTransactionStatus::FAILED_TO_OPEN,
                'request_payload' => $payload,
                'error_code' => get_class($e),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        DB::transaction(function () use ($cascade_deal, $provider_model, $payload, $response_payload): void {
            $order = $this->findInternalOrder($response_payload);

            $transaction = CascadeTransaction::create([
                'cascade_deal_id' => $cascade_deal->id,
                'provider_id' => $provider_model->id,
                'status' => CascadeTransactionStatus::ACCEPTED,
                'provider_deal_id' => Arr::get($response_payload, 'provider_deal_id'),
                'request_payload' => $payload,
                'response_payload' => $response_payload,
            ]);

            $cascade_deal->update($this->cascadeDealWinnerAttributes(
                cascade_deal: $cascade_deal,
                provider_model: $provider_model,
                transaction: $transaction,
                response_payload: $response_payload,
                order: $order,
            ));
        });
    }

    private function findInternalOrder(array $response_payload): ?Order
    {
        $provider_deal_id = Arr::get($response_payload, 'provider_deal_id');

        if (! $provider_deal_id) {
            return null;
        }

        return Order::withoutGlobalScopes()
            ->where('uuid', $provider_deal_id)
            ->first();
    }

    private function cascadeDealWinnerAttributes(
        CascadeDeal $cascade_deal,
        CascadeProvider $provider_model,
        CascadeTransaction $transaction,
        array $response_payload,
        ?Order $order,
    ): array {
        return [
            'order_id' => $order?->id,
            'amount' => $order?->amount ?? $cascade_deal->amount,
            'initial_amount' => $order?->base_amount ?? $cascade_deal->initial_amount,
            'currency' => $order?->currency ?? $cascade_deal->currency,
            'debit' => $order?->total_profit ?? Money::fromPrecision('0', 'USDT'),
            'credit' => $order?->merchant_profit ?? Money::fromPrecision('0', 'USDT'),
            'service_profit' => $order?->service_profit ?? Money::fromPrecision('0', 'USDT'),
            'usdt_amount' => $order?->merchant_profit ?? Money::fromPrecision('0', 'USDT'),
            'fee' => null,
            'fee_rate' => null,
            'market' => $order?->market ?? $cascade_deal->market,
            'conversion_price' => $order?->conversion_price ?? $cascade_deal->conversion_price,
            'rate_fixed_at' => $cascade_deal->rate_fixed_at ?? $order?->created_at,
            'status' => $order?->status ?? OrderStatus::PENDING,
            'sub_status' => $order?->sub_status ?? OrderSubStatus::WAITING_FOR_DETAILS_TO_BE_SELECTED,
            'selected_provider_id' => $provider_model->id,
            'selected_transaction_id' => $transaction->id,
            'gateway' => Arr::get($response_payload, 'gateway'),
            'details' => Arr::get($response_payload, 'details'),
            'finished_at' => $order?->finished_at,
        ];
    }
}
