<?php

namespace App\Http\Resources;

use App\Models\WithdrawalAddress;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WithdrawalAddressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WithdrawalAddress $this */
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'masked_address' => $this->masked_address,
            'currency' => 'USDT',
            'network' => 'TRC20',
            'withdrawals_count' => (int) ($this->resource->getAttribute('withdrawals_count') ?? 0),
            'withdrawals_amount' => array_key_exists('withdrawals_amount', $this->resource->getAttributes())
                ? Money::fromUnits($this->withdrawalsAmountUnits(), Currency::USDT()->getCode())->toBeauty()
                : null,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }

    private function withdrawalsAmountUnits(): string
    {
        $amount = (string) ($this->resource->getAttribute('withdrawals_amount') ?? '0');

        return explode('.', $amount)[0] ?: '0';
    }
}
