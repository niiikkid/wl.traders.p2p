<?php

namespace App\Contracts;

use App\Enums\BalanceType;
use App\Exceptions\WalletDepositException;
use App\Models\User;
use App\Models\WalletDepositAddress;
use App\Models\WalletDepositInvoice;
use App\Services\Money\Money;

interface WalletDepositServiceContract
{
    /**
     * Create an internal USDT/TRON deposit invoice: allocate a pool address,
     * store an immutable payment-instruction snapshot, and generate a private QR.
     *
     * @throws WalletDepositException
     */
    public function createInvoice(int $walletID, Money $amount, BalanceType $balanceType): WalletDepositInvoice;

    /**
     * Read the blockchain for one open invoice and settle it if a matching
     * confirmed transfer is found. Safe to run repeatedly (idempotent credit).
     */
    public function scan(WalletDepositInvoice $invoice): void;

    /**
     * Admin-only: attach a transaction hash to an invoice after manual review.
     * The transaction is re-fetched fresh from the chain before applying.
     *
     * @throws WalletDepositException
     */
    public function manualAttach(WalletDepositInvoice $invoice, string $txid, User $admin, ?string $note): WalletDepositInvoice;

    /**
     * Admin diagnostics: refresh the last known on-chain balance of a pool address.
     */
    public function refreshAddressBalance(WalletDepositAddress $address): WalletDepositAddress;

    /**
     * Admin manual review: incoming transfers for an invoice address with match flags.
     *
     * @return list<array<string, mixed>>
     */
    public function addressTransfersForReview(WalletDepositInvoice $invoice): array;
}
