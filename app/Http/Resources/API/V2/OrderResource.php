<?php

declare(strict_types=1);

namespace App\Http\Resources\API\V2;

use App\Models\CascadeDeal;
use App\Services\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @mixin CascadeDeal
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /**
         * @var CascadeDeal $this
         */
        $gateway = $this->gateway ?? [];
        $details = $this->details ?? [];
        $fiatCurrencyCode = $this->currency?->getCode();

        return [
            'id' => $this->uuid,
            'external_id' => $this->external_id,
            'merchant_id' => $this->merchant->uuid,
            'status' => $this->status->value,
            'sub_status' => $this->sub_status->value,
            'amounts' => [
                'amount' => $this->formatMoney($this->amount, $fiatCurrencyCode),
                'initial_amount' => $this->formatMoney($this->initial_amount, $fiatCurrencyCode),
                'exchanged_amount' => $this->formatMoney($this->usdt_amount, 'USDT'),
                'merchant_credit' => $this->formatMoney($this->credit, null),
            ],
            'exchange_rate' => [
                'market' => $this->market?->value,
                'price' => $this->formatMoney($this->conversion_price, null),
                'fixed_at' => $this->rate_fixed_at?->toIso8601String(),
            ],
            'payin_method' => $this->payment_method?->value,
            'payin_details' => empty($details) ? null : [
                'bank_name' => Arr::get($gateway, 'name'),
                'value' => Arr::get($details, 'value'),
                'recipient_name' => Arr::get($details, 'initials'),
            ],
            'manual_acquiring' => $this->manual_control === null ? null : [
                'confirmation_type' => $this->manual_control->confirmationType,
                'reject_reason' => $this->manual_control->rejectReason,
            ],
            'dispute' => [
                'status' => $this->dispute_status?->value,
                'reason' => $this->dispute_reason,
                'canceled_at' => $this->dispute_canceled_at?->toIso8601String(),
            ],
            'finished_at' => $this->finished_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'current_server_time' => now()->toIso8601String(),
        ];
    }

    private function formatMoney(?Money $money, ?string $currencyCode): ?array
    {
        if (! $money) {
            return null;
        }

        return [
            'value' => $money->toBeauty(),
            'currency' => strtoupper($currencyCode ?? $money->getCurrency()->getCode()),
        ];
    }
}
