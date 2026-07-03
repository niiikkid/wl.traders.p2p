<?php

namespace App\Http\Resources;

use App\Models\WalletDepositInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletDepositInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WalletDepositInvoice $this */
        return [
            'id' => $this->uuid,
            'status' => $this->status->value,
            'amount' => $this->amount->toBeauty(),
            'amount_received' => $this->amount_received?->toBeauty(),
            'currency' => $this->currency->getCode(),
            'network' => $this->network->value,
            'balance_type' => $this->balance_type->value,
            'merchant' => $this->wallet?->merchant ? [
                'id' => $this->wallet->merchant->id,
                'uuid' => $this->wallet->merchant->uuid,
                'name' => $this->wallet->merchant->name,
            ] : null,
            'address' => $this->address,
            'qr_url' => route('deposit.invoices.qr', $this->resource),
            'txid' => $this->txid,
            'tx_explorer_url' => $this->txid
                ? rtrim((string) config('services.trongrid.tronscan_base_url'), '/').'/#/transaction/'.$this->txid
                : null,
            'confirmations' => $this->confirmations,
            'required_confirmations' => (int) config('services.wallet_deposit.min_confirmations', 10),
            'match_type' => $this->match_type?->value,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
