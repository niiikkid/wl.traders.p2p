<?php

namespace App\Http\Resources;

use App\Models\Merchant;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var Merchant $this
         */
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'domain' => $this->domain,
            'user_id' => $this->user_id,
            'active' => $this->active,
            'today_profit' => $this->when(isset($this->orders_sum_merchant_profit), Money::fromUnits($this->orders_sum_merchant_profit ?? 0, Currency::USDT())->toBeauty()),
            'profit_currency' => $this->when(isset($this->orders_sum_merchant_profit), Currency::USDT()->getCode()),
            'owner' => [
                'id' => $this->user->id,
                'email' => $this->user->email,
            ],
            'callback_url' => $this->callback_url,
            'payout_callback_url' => $this->payout_callback_url,
            'geos' => collect($this->settings['geos'] ?? [])
                ->map(fn ($market, $currency) => [
                    'currency' => strtolower($currency),
                    'market' => $market,
                ])
                ->values(),
            'max_order_wait_time' => $this->max_order_wait_time,
            'min_order_amounts' => !empty($this->min_order_amounts) ? $this->min_order_amounts : null,
            'categories' => $this->whenLoaded('categories', function () {
                return $this->categories?->pluck('id')->toArray();
            }),
            'validated_at' => $this->validated_at?->toDateTimeString(),
            'banned_at' => $this->banned_at?->toDateTimeString(),
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
