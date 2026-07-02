<?php

namespace App\Http\Resources\Admin;

use App\Models\WalletDepositAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletDepositAddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WalletDepositAddress $this */
        return [
            'id' => $this->id,
            'currency' => $this->currency,
            'network' => $this->network->value,
            'address' => $this->address,
            'label' => $this->label,
            'is_active' => (bool) $this->is_active,
            'balance' => $this->balance_units?->toBeauty(),
            'open_invoices_count' => $this->whenCounted('invoices'),
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
