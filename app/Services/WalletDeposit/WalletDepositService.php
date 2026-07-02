<?php

namespace App\Services\WalletDeposit;

use App\Contracts\WalletDepositServiceContract;
use App\DTO\Tron\TronTransfer;
use App\Enums\BalanceType;
use App\Enums\NetworkEnum;
use App\Enums\WalletDepositInvoiceStatus;
use App\Enums\WalletDepositMatchType;
use App\Exceptions\WalletDepositException;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletDepositAddress;
use App\Models\WalletDepositInvoice;
use App\Services\Blockchain\TronGridClient;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Services\WalletDeposit\Features\DepositAddressAllocator;
use App\Services\WalletDeposit\Features\DepositInvoiceQrGenerator;
use App\Services\WalletDeposit\Features\DepositInvoiceScanner;
use App\Services\WalletDeposit\Features\DepositInvoiceSettlement;
use App\Utils\Transaction;
use Illuminate\Support\Str;

class WalletDepositService implements WalletDepositServiceContract
{
    public function createInvoice(int $walletID, Money $amount, BalanceType $balanceType): WalletDepositInvoice
    {
        if (! $amount->greaterThanZero()) {
            throw WalletDepositException::invalidAmount();
        }

        return Transaction::run(function () use ($walletID, $amount, $balanceType) {
            $wallet = Wallet::query()->whereKey($walletID)->lockForUpdate()->first();

            $address = (new DepositAddressAllocator)->allocate($amount);

            $expiresInMinutes = (int) config('services.wallet_deposit.invoice_expires_in_minutes', 30);
            $pollGraceMinutes = (int) config('services.wallet_deposit.poll_grace_minutes', 60);
            $expiresAt = now()->addMinutes($expiresInMinutes);

            $invoice = WalletDepositInvoice::create([
                'wallet_id' => $wallet->id,
                'balance_type' => $balanceType,
                'deposit_address_id' => $address->id,
                'address' => $address->address,
                'currency' => Currency::USDT(),
                'network' => NetworkEnum::TRX,
                'amount' => $amount,
                'status' => WalletDepositInvoiceStatus::PENDING,
                'confirmations' => 0,
                'expires_at' => $expiresAt,
                'poll_until_at' => $expiresAt->copy()->addMinutes($pollGraceMinutes),
            ]);

            (new DepositInvoiceQrGenerator)->generate($invoice);

            return $invoice->fresh();
        });
    }

    public function scan(WalletDepositInvoice $invoice): void
    {
        (new DepositInvoiceScanner)->scan($invoice);
    }

    public function manualAttach(WalletDepositInvoice $invoice, string $txid, User $admin, ?string $note): WalletDepositInvoice
    {
        $transfer = (new TronGridClient)->findAddressTransfer($invoice->address, $txid);

        if ($transfer === null) {
            throw WalletDepositException::transferNotFound();
        }

        return (new DepositInvoiceSettlement)->attach(
            $invoice,
            $transfer,
            WalletDepositMatchType::MANUAL,
            $admin,
            $note,
        );
    }

    public function refreshAddressBalance(WalletDepositAddress $address): WalletDepositAddress
    {
        try {
            $balance = (new TronGridClient)->getAddressUsdtBalance($address->address);

            $address->update([
                'balance_units' => $balance,
                'last_checked_at' => now(),
                'last_error' => null,
            ]);
        } catch (\Throwable $e) {
            $address->update([
                'last_checked_at' => now(),
                'last_error' => Str::limit($e->getMessage(), 190, ''),
            ]);
        }

        return $address->fresh();
    }

    public function addressTransfersForReview(WalletDepositInvoice $invoice): array
    {
        $client = new TronGridClient;
        $pageSize = (int) config('services.wallet_deposit.manual_review_page_size', 50);

        $transfers = $client->incomingUsdtTransfers($invoice->address, null, $pageSize);

        return array_map(fn (TronTransfer $transfer): array => [
            'txid' => $transfer->txid,
            'from' => $transfer->from,
            'to' => $transfer->to,
            'amount' => $transfer->amount->toBeauty(),
            'currency' => 'USDT',
            'network' => NetworkEnum::TRX->value,
            'timestamp' => $transfer->timestamp->toIso8601String(),
            'matches_invoice_amount' => $transfer->amount->equals($invoice->amount),
            'inside_invoice_window' => $transfer->timestamp->betweenIncluded($invoice->created_at, $invoice->expires_at),
            'already_attached' => $this->txidAttachedElsewhere($transfer->txid, $invoice->id),
            'explorer_url' => $client->explorerUrl($transfer->txid),
        ], $transfers);
    }

    private function txidAttachedElsewhere(string $txid, int $invoiceID): bool
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
}
