<?php

namespace App\Services\WalletDeposit\Features;

use App\DTO\Tron\TronTransfer;
use App\Enums\WalletDepositInvoiceStatus;
use App\Enums\WalletDepositMatchType;
use App\Models\WalletDepositInvoice;
use App\Services\Blockchain\TronGridClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads the blockchain for one invoice and makes a conservative matching decision.
 * It never guesses: no match leaves the invoice pending; multiple matches are
 * flagged for manual review; exactly one exact match is attached automatically.
 */
class DepositInvoiceScanner
{
    public function __construct(
        private readonly TronGridClient $client = new TronGridClient,
        private readonly DepositInvoiceSettlement $settlement = new DepositInvoiceSettlement,
    ) {}

    public function scan(WalletDepositInvoice $invoice): void
    {
        if ($invoice->status->isFinal()) {
            return;
        }

        if ($this->expireIfPastWindow($invoice)) {
            return;
        }

        try {
            if ($invoice->status->equals(WalletDepositInvoiceStatus::PROCESSING) && $invoice->txid) {
                $this->settlement->reconfirm($invoice, $this->client->confirmationsFor($invoice->txid));
            } elseif ($invoice->status->equals(WalletDepositInvoiceStatus::PENDING)) {
                $this->scanPending($invoice);
            }

            $this->touch($invoice);
        } catch (Throwable $e) {
            WalletDepositInvoice::query()
                ->whereKey($invoice->id)
                ->update([
                    'last_checked_at' => now(),
                    'error_message' => Str::limit($e->getMessage(), 190, ''),
                ]);

            Log::warning('Wallet deposit invoice scan failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            report($e);
        }
    }

    private function scanPending(WalletDepositInvoice $invoice): void
    {
        $transfers = $this->client->incomingUsdtTransfers($invoice->address, $invoice->created_at, 200);

        $matches = array_values(array_filter(
            $transfers,
            fn (TronTransfer $transfer): bool => $transfer->amount->equals($invoice->amount)
                && $transfer->timestamp->betweenIncluded($invoice->created_at, $invoice->expires_at)
                && ! $this->txidUsedElsewhere($transfer->txid, $invoice->id),
        ));

        if ($matches === []) {
            return;
        }

        if (count($matches) > 1) {
            WalletDepositInvoice::query()
                ->whereKey($invoice->id)
                ->update(['error_message' => 'multiple_matches']);

            return;
        }

        $transfer = $matches[0];
        $confirmations = $this->client->confirmationsFor($transfer->txid);

        $this->settlement->attach(
            $invoice,
            $transfer->withConfirmations($confirmations),
            WalletDepositMatchType::AUTOMATIC,
        );
    }

    private function expireIfPastWindow(WalletDepositInvoice $invoice): bool
    {
        if ($invoice->status->notEquals(WalletDepositInvoiceStatus::PENDING)) {
            return false;
        }

        if ($invoice->txid) {
            return false;
        }

        if (now()->lessThan($invoice->expires_at)) {
            return false;
        }

        $affected = WalletDepositInvoice::query()
            ->whereKey($invoice->id)
            ->where('status', WalletDepositInvoiceStatus::PENDING->value)
            ->whereNull('txid')
            ->update([
                'status' => WalletDepositInvoiceStatus::EXPIRED->value,
                'finalized_at' => now(),
                'last_checked_at' => now(),
            ]);

        return $affected > 0;
    }

    private function txidUsedElsewhere(string $txid, int $invoiceID): bool
    {
        return WalletDepositInvoice::query()
            ->where('txid', $txid)
            ->whereKeyNot($invoiceID)
            ->whereIn('status', [
                WalletDepositInvoiceStatus::PROCESSING->value,
                WalletDepositInvoiceStatus::PAID->value,
            ])
            ->exists();
    }

    private function touch(WalletDepositInvoice $invoice): void
    {
        WalletDepositInvoice::query()
            ->whereKey($invoice->id)
            ->update(['last_checked_at' => now()]);
    }
}
