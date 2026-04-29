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
        $merchant = $this->merchant ?? queries()->merchant()->findByID($this->merchant_id);
        $gateway = $this->gateway ?? [];
        $details = $this->details ?? [];
        $fiatCurrencyCode = $this->currency?->getCode();

        return [
            'payin_id' => $this->uuid,
            'external_id' => $this->external_id,
            'merchant_id' => $merchant->uuid,
            'status' => $this->status->value,
            'sub_status' => $this->sub_status->value,
            'amount' => $this->formatMoney($this->amount, $fiatCurrencyCode),
            'initial_amount' => $this->formatMoney($this->initial_amount, $fiatCurrencyCode),
            'converted_amount' => $this->formatMoney($this->usdt_amount, 'USDT'),
            'profit' => $this->formatMoney($this->credit, null),
            'rate' => [
                'market' => $this->market?->value,
                'price' => $this->formatMoney($this->conversion_price, null),
                'fixed_at' => $this->rate_fixed_at?->toIso8601String(),
            ],
            'payment_method' => $this->payment_method?->value,
            'details' => empty($details) ? null : [
                'gateway' => Arr::get($gateway, 'name'),
                'initials' => Arr::get($details, 'initials'),
                'value' => Arr::get($details, 'value'),
            ],
            'manual_control_acquiring' => $this->manual_control === null ? null : [
                'confirmation_type' => $this->manual_control->confirmationType,
                'reject_reason' => $this->manual_control->rejectReason,
            ],
            'dispute' => [
                'status' => $this->dispute_status?->value,
                'reason' => $this->dispute_reason,
                'canceled_at' => $this->dispute_canceled_at?->toIso8601String(),
            ],
            'callback_url' => $this->callback_url,
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
