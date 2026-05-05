<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\CascadeProviderServiceContract;
use App\Contracts\CascadeServiceContract;
use App\Enums\ProviderType;
use App\Exceptions\CascadeException;
use App\Models\CascadeDeal;
use App\Models\CascadeProvider;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CascadeInternalProviderCallbackJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 10;

    public int $tries = 6;

    /**
     * @var list<int>
     */
    public array $backoff = [3, 5, 10, 30, 60];

    public function __construct(
        public int $orderId,
    ) {
        $this->afterCommit();
        $this->onQueue('cascade-provider-attempts');
    }

    public function handle(CascadeProviderServiceContract $providers, CascadeServiceContract $cascade): void
    {
        $order = Order::withoutGlobalScopes()
            ->with(['paymentGateway', 'paymentDetail', 'dispute'])
            ->find($this->orderId);

        if (! $order instanceof Order) {
            return;
        }

        $deal = CascadeDeal::query()
            ->with(['selectedProvider', 'selectedTransaction'])
            ->where('order_id', $order->id)
            ->first();

        if (! $deal instanceof CascadeDeal) {
            return;
        }

        $providerModel = $deal->selectedProvider;

        if (
            ! $providerModel instanceof CascadeProvider
            || ! $providerModel->provider_type->equals(ProviderType::INTERNAL)
        ) {
            return;
        }

        $provider = $providers->getProviderByModel($providerModel);

        if (! $provider) {
            throw CascadeException::make('Интеграция провайдера каскада недоступна.');
        }

        $cascade->handleProviderCallbackPayload(
            cascadeProvider: $providerModel,
            payload: $this->payload($deal, $order),
            accessToken: null,
            url: $provider->providerApiLogUrl('callback', $deal, ['provider_deal_id' => $order->uuid]),
            validateAccessToken: false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CascadeDeal $deal, Order $order): array
    {
        return [
            'cascade_deal_uuid' => $deal->uuid,
            'order_id' => $order->uuid,
            'status' => $order->status?->value,
            'sub_status' => $order->sub_status?->value,
            'amount' => $order->amount?->toPrecision(),
            'initial_amount' => $order->base_amount?->toPrecision(),
            'currency' => $order->currency?->getCode(),
            'debit' => $order->total_profit?->toPrecision(),
            'credit' => $order->merchant_profit?->toPrecision(),
            'service_profit' => $order->service_profit?->toPrecision(),
            'usdt_amount' => $order->total_profit?->toPrecision(),
            'market' => $order->market?->value,
            'conversion_price' => $order->conversion_price?->toPrecision(),
            'rate_fixed_at' => $order->rate_fixed_at?->getTimestamp(),
            'manual_control_acquiring' => $order->manual_control_acquiring,
            'manual_control_confirmation_type' => $order->manual_control_confirmation_type?->value,
            'manual_control_reject_reason' => $order->manual_control_reject_reason,
            'dispute' => $order->dispute ? [
                'dispute_id' => (string) $order->dispute->id,
                'status' => $order->dispute->status?->value,
                'reason' => $order->dispute->reason,
                'updated_at' => $order->dispute->updated_at?->getTimestamp(),
            ] : null,
            'gateway' => [
                'code' => $order->paymentGateway?->code,
                'name' => $order->paymentGateway?->name,
                'logo_link' => null,
            ],
            'details' => [
                'type' => $order->manual_control_acquiring ? null : $order->paymentDetail?->detail_type?->value,
                'value' => $order->manual_control_acquiring ? null : $order->paymentDetail?->detail,
                'initials' => $order->manual_control_acquiring ? null : $order->paymentDetail?->initials,
            ],
            'created_at' => $order->created_at?->getTimestamp(),
            'finished_at' => $order->finished_at?->getTimestamp(),
            'occurred_at' => now()->getTimestamp(),
        ];
    }
}
