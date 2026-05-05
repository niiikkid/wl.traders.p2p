<?php

declare(strict_types=1);

namespace App\Http\Resources\API\V2;

use App\Models\Payout\Payout;
use App\Services\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payout
 */
class PayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'external_id' => $this->external_id,
            'merchant_id' => $this->merchant?->uuid,
            'status' => $this->status->value,
            'amounts' => [
                'amount' => $this->formatMoney($this->amount_fiat, $this->amount_fiat_currency),
                'exchanged_amount' => $this->formatMoney($this->usdt_body, $this->usdt_body_currency),
                'merchant_debit' => $this->formatMoney($this->merchant_debit, $this->merchant_debit_currency),
                'commission' => $this->formatMoney($this->total_fee, $this->total_fee_currency),
            ],
            'exchange_rate' => [
                'price' => $this->formatMoney($this->conversion_price, $this->conversion_price_currency),
                'fixed_at' => $this->rate_fixed_at?->toIso8601String(),
            ],
            'payout_method' => $this->payout_method_type->value,
            'payout_details' => [
                'bank_name' => $this->bank_name,
                'value' => $this->requisites,
                'recipient_name' => $this->initials,
            ],
            'receipts_url' => $this->hasReceipts()
                ? route('api.v2.payout.receipts.index', ['payout' => $this->uuid])
                : null,
            'finished_at' => ($this->completed_at ?? $this->canceled_at)?->toIso8601String(),
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

    private function hasReceipts(): bool
    {
        if ($this->receipt_path) {
            return true;
        }

        if ($this->relationLoaded('receipts')) {
            return $this->receipts->isNotEmpty();
        }

        return $this->receipts()->exists();
    }
}
