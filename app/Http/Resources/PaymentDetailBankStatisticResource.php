<?php

namespace App\Http\Resources;

use App\Models\PaymentGateway;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentDetailBankStatisticResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var PaymentGateway $this
         */
        return [
            'id' => $this->id,
            'name' => $this->name_with_currency,
            'code' => $this->code,
            'logo_path' => $this->logo ? asset('storage/logos/'.$this->logo) : null,
            'payment_details_count' => $this->payment_details_count,
            'payment_details_percent' => (float) ($this->payment_details_percent ?? 0),
            'successful_orders_total_turnover_usdt' => Money::fromUnits(
                (int) ($this->successful_orders_total_turnover_usdt ?? 0),
                Currency::USDT()
            )->toBeauty(),
            'successful_orders_total_turnover_percent' => (float) ($this->successful_orders_total_turnover_percent ?? 0),
        ];
    }
}
