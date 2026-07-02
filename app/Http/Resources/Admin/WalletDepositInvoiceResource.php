<?php

namespace App\Http\Resources\Admin;

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
            'address' => $this->address,
            'txid' => $this->txid,
            'tx_explorer_url' => $this->txid
                ? rtrim((string) config('services.trongrid.tronscan_base_url'), '/').'/#/transaction/'.$this->txid
                : null,
            'confirmations' => $this->confirmations,
            'required_confirmations' => (int) config('services.wallet_deposit.min_confirmations', 10),
            'match_type' => $this->match_type?->value,
            'balance_type' => $this->balance_type->value,
            'user' => [
                'id' => $this->wallet?->user?->id,
                'email' => $this->wallet?->user?->email,
            ],
            'resolved_by' => $this->resolvedBy?->email,
            'resolution_note' => $this->resolution_note,
            'matched_at' => $this->matched_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
