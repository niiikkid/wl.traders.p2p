<?php

namespace App\Services\WalletDeposit\Features;

use App\DTO\Tron\TronTransfer;
use App\Enums\WalletDepositInvoiceStatus;
use App\Enums\WalletDepositMatchType;
use App\Exceptions\InvoiceException;
use App\Exceptions\WalletDepositException;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WalletDepositInvoice;
use App\Services\Notification\Events\WalletDepositPaidNotificationEvent;
use App\Utils\Transaction;

/**
 * The single safe settlement point for internal deposit invoices.
 *
 * All balance credits go through the existing InvoiceService::deposit path with
 * a deterministic transaction id, so callbacks, polling retries, and manual
 * clicks credit the wallet exactly once.
 */
class DepositInvoiceSettlement
{
    private bool $paidInThisRun = false;

    /**
     * Attach a blockchain transfer to an invoice and credit it if confirmed.
     *
     * @throws WalletDepositException
     */
    public function attach(
        WalletDepositInvoice $invoice,
        TronTransfer $transfer,
        WalletDepositMatchType $matchType,
        ?User $admin = null,
        ?string $note = null,
    ): WalletDepositInvoice {
        $this->paidInThisRun = false;

        $result = Transaction::run(function () use ($invoice, $transfer, $matchType, $admin, $note) {
            $locked = WalletDepositInvoice::query()->whereKey($invoice->id)->lockForUpdate()->first();

            if ($locked->status->isFinal()) {
                if ($matchType->equals(WalletDepositMatchType::MANUAL)) {
                    throw WalletDepositException::invoiceAlreadyFinal();
                }

                return $locked;
            }

            if ($transfer->to !== $locked->address) {
                throw WalletDepositException::recipientMismatch();
            }

            if ($transfer->contract !== (string) config('services.trongrid.usdt_contract')) {
                throw WalletDepositException::contractMismatch();
            }

            $this->guardTransferNotUsedElsewhere($transfer->txid, $locked->id);

            $isManual = $matchType->equals(WalletDepositMatchType::MANUAL);

            // Automatic settlement never credits a wrong amount; the scanner only
            // passes exact matches, this guard is defense in depth.
            if (! $isManual && ! $transfer->amount->equals($locked->amount)) {
                throw WalletDepositException::amountMismatch();
            }

            $locked->txid = $transfer->txid;
            $locked->amount_received = $transfer->amount;
            $locked->match_type = $matchType;
            $locked->matched_at = $locked->matched_at ?? now();
            $locked->confirmations = $transfer->confirmations ?? 0;
            $locked->error_message = null;

            if ($isManual) {
                // Manual resolution credits the actual on-chain amount.
                $locked->amount = $transfer->amount;
                $locked->resolved_by_user_id = $admin?->id;
                $locked->resolution_note = $note;
            }

            $this->finalizeIfConfirmed($locked);

            return $locked;
        });

        $this->notifyIfJustPaid($result);

        return $result;
    }

    /**
     * Refresh confirmations for a processing invoice and credit it once enough are reached.
     */
    public function reconfirm(WalletDepositInvoice $invoice, ?int $confirmations): WalletDepositInvoice
    {
        $this->paidInThisRun = false;

        $result = Transaction::run(function () use ($invoice, $confirmations) {
            $locked = WalletDepositInvoice::query()->whereKey($invoice->id)->lockForUpdate()->first();

            if ($locked->status->notEquals(WalletDepositInvoiceStatus::PROCESSING) || ! $locked->txid) {
                return $locked;
            }

            if ($confirmations !== null) {
                $locked->confirmations = $confirmations;
            }

            $this->finalizeIfConfirmed($locked);

            return $locked;
        });

        $this->notifyIfJustPaid($result);

        return $result;
    }

    private function finalizeIfConfirmed(WalletDepositInvoice $invoice): void
    {
        $minConfirmations = (int) config('services.wallet_deposit.min_confirmations', 10);

        if ($invoice->confirmations >= $minConfirmations) {
            $settlementKey = "wallet-deposit-invoice:{$invoice->id}:credit";

            try {
                services()->invoice()->deposit(
                    walletID: $invoice->wallet_id,
                    amount: $invoice->amount,
                    balanceType: $invoice->balance_type,
                    transactionID: $settlementKey,
                    txHash: $invoice->txid,
                );
            } catch (InvoiceException) {
                // The historical Invoice already exists — the wallet was credited before. Idempotent.
            }

            $invoice->settled_invoice_id = Invoice::query()
                ->where('transaction_id', $settlementKey)
                ->value('id');
            $invoice->status = WalletDepositInvoiceStatus::PAID;
            $invoice->finalized_at = now();
            $this->paidInThisRun = true;
        } else {
            $invoice->status = WalletDepositInvoiceStatus::PROCESSING;
        }

        $invoice->save();
    }

    private function notifyIfJustPaid(WalletDepositInvoice $invoice): void
    {
        if (! $this->paidInThisRun) {
            return;
        }

        services()->notification()->dispatch(new WalletDepositPaidNotificationEvent($invoice));
    }

    private function guardTransferNotUsedElsewhere(string $txid, int $invoiceID): void
    {
        $used = WalletDepositInvoice::query()
            ->where('txid', $txid)
            ->whereKeyNot($invoiceID)
            ->whereIn('status', [
                WalletDepositInvoiceStatus::PROCESSING->value,
                WalletDepositInvoiceStatus::PAID->value,
            ])
            ->lockForUpdate()
            ->exists();

        if ($used) {
            throw WalletDepositException::transferAlreadyAttached();
        }
    }
}
