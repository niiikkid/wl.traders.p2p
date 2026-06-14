<?php

namespace App\Services\Invoice;

use App\Contracts\InvoiceServiceContract;
use App\Enums\BalanceType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\TransactionType;
use App\Exceptions\InvoiceException;
use App\Models\Invoice;
use App\Models\Wallet;
use App\Services\External\InvoiceApiClient;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Utils\Transaction;

class InvoiceService implements InvoiceServiceContract
{
    public function createWithdrawal(int $walletID, Money $amount, ?string $address, BalanceType $balanceType): Invoice
    {
        return Transaction::run(function () use ($walletID, $amount, $address, $balanceType) {
            $wallet = Wallet::where('id', $walletID)->lockForUpdate()->first();

            $totalAvailableBalance = services()->wallet()->getTotalAvailableBalance($wallet, $balanceType);

            if ($amount->greaterThan($totalAvailableBalance)) {
                throw InvoiceException::insufficientBalance();
            }

            $invoice = Invoice::create([
                'amount' => $amount,
                'currency' => $amount->getCurrency(),
                'address' => $address,
                'type' => InvoiceType::WITHDRAWAL,
                'balance_type' => $balanceType,
                'status' => InvoiceStatus::PENDING,
                'wallet_id' => $wallet->id,
            ]);

            services()->wallet()
                ->takeFromBalance(
                    walletID: $wallet->id,
                    amount: $amount,
                    transactionType: TransactionType::WITHDRAWAL_BY_USER,
                    balanceType: $balanceType
                );

            return $invoice;
        });
    }

    public function finishAutoWithdrawal(int $paymentID, string $status, ?string $txHash = null): Invoice
    {
        return Transaction::run(function () use ($paymentID, $status, $txHash) {
            $invoice = Invoice::where('id', $paymentID)->lockForUpdate()->first();

            if (! $invoice->external_id) {
                throw InvoiceException::onlyAutoWithdrawals();
            }

            if ($invoice->type->notEquals(InvoiceType::WITHDRAWAL)) {
                throw InvoiceException::invalidInvoiceType();
            }

            if ($invoice->status->notEquals(InvoiceStatus::PENDING)) {
                throw InvoiceException::invoiceAlreadyFinished();
            }

            if ($status === 'success') {
                $invoice->update([
                    'status' => InvoiceStatus::SUCCESS,
                    'tx_hash' => $txHash,
                ]);
            } elseif ($status === 'fail') {
                $invoice->update([
                    'status' => InvoiceStatus::FAIL,
                    'tx_hash' => $txHash,
                ]);

                services()->wallet()->giveToBalance(
                    walletID: $invoice->wallet->id,
                    amount: $invoice->amount,
                    transactionType: TransactionType::ROLLBACK_FOR_USER_WITHDRAWAL,
                    balanceType: $invoice->balance_type
                );
            }

            return $invoice;
        });
    }

    public function finishWithdrawal(int $invoiceID): void
    {
        Transaction::run(function () use ($invoiceID) {
            $invoice = Invoice::where('id', $invoiceID)->lockForUpdate()->first();

            if ($invoice->type->notEquals(InvoiceType::WITHDRAWAL)) {
                throw InvoiceException::invalidInvoiceType();
            }

            if ($invoice->status->notEquals(InvoiceStatus::PENDING)) {
                throw InvoiceException::invoiceAlreadyFinished();
            }

            $invoice->update(['status' => InvoiceStatus::SUCCESS]);
        });
    }

    public function cancelWithdrawal(int $invoiceID): void
    {
        Transaction::run(function () use ($invoiceID) {
            $invoice = Invoice::where('id', $invoiceID)->lockForUpdate()->first();

            if ($invoice->type->notEquals(InvoiceType::WITHDRAWAL)) {
                throw InvoiceException::invalidInvoiceType();
            }

            if ($invoice->status->notEquals(InvoiceStatus::PENDING)) {
                throw InvoiceException::invoiceAlreadyFinished();
            }

            $invoice->update(['status' => InvoiceStatus::FAIL]);

            services()->wallet()->giveToBalance(
                walletID: $invoice->wallet->id,
                amount: $invoice->amount,
                transactionType: TransactionType::ROLLBACK_FOR_USER_WITHDRAWAL,
                balanceType: $invoice->balance_type
            );
        });
    }

    public function deposit(int $walletID, Money $amount, BalanceType $balanceType, ?string $transactionID = null, ?string $txHash = null): void
    {
        Transaction::run(function () use ($walletID, $amount, $balanceType, $transactionID, $txHash) {
            $wallet = Wallet::where('id', $walletID)->lockForUpdate()->first();

            if ($transactionID && Invoice::where('transaction_id', $transactionID)->exists()) {
                throw InvoiceException::invoiceAlreadyExists();
            }

            Invoice::create([
                'amount' => $amount,
                'currency' => Currency::USDT(),
                'address' => null,
                'type' => InvoiceType::DEPOSIT,
                'balance_type' => $balanceType,
                'status' => InvoiceStatus::SUCCESS,
                'transaction_id' => $transactionID,
                'wallet_id' => $wallet->id,
                'tx_hash' => $txHash,
            ]);

            services()->wallet()
                ->giveToBalance(
                    walletID: $wallet->id,
                    amount: $amount,
                    transactionType: $transactionID ? TransactionType::DEPOSIT_BY_USER : TransactionType::DEPOSIT_BY_ADMIN,
                    balanceType: $balanceType
                );
        });
    }

    public function withdraw(int $walletID, Money $amount, BalanceType $balanceType): void
    {
        Transaction::run(function () use ($walletID, $amount, $balanceType) {
            $wallet = Wallet::where('id', $walletID)->lockForUpdate()->first();

            $totalAvailableBalance = services()->wallet()->getTotalAvailableBalance($wallet, $balanceType);

            if ($amount->greaterThan($totalAvailableBalance)) {
                throw InvoiceException::insufficientBalance();
            }

            Invoice::create([
                'amount' => $amount,
                'currency' => Currency::USDT(),
                'address' => null,
                'type' => InvoiceType::WITHDRAWAL,
                'balance_type' => $balanceType,
                'status' => InvoiceStatus::SUCCESS,
                'wallet_id' => $wallet->id,
            ]);

            services()->wallet()
                ->takeFromBalance(
                    walletID: $wallet->id,
                    amount: $amount,
                    transactionType: TransactionType::WITHDRAWAL_BY_ADMIN,
                    balanceType: $balanceType
                );
        });
    }

    /**
     * Создание депозита через внешний сервис: создаём PENDING-инвойс у нас и инвойс у провайдера атомарно
     * Возвращает массив с нашим инвойсом и ответом провайдера (в т.ч. payment_url)
     */
    public function createExternalDeposit(int $walletID, Money $amount, BalanceType $balanceType): array
    {
        return Transaction::run(function () use ($walletID, $amount, $balanceType) {
            $wallet = Wallet::where('id', $walletID)->lockForUpdate()->first();

            // Создаём локальный инвойс в ожидании оплаты
            $invoice = Invoice::create([
                'amount' => $amount,
                'currency' => $amount->getCurrency(),
                'address' => null,
                'type' => InvoiceType::DEPOSIT,
                'balance_type' => $balanceType,
                'status' => InvoiceStatus::PENDING,
                'wallet_id' => $wallet->id,
            ]);

            // Вызываем внешний сервис: external_invoice_id = наш ID
            $client = new InvoiceApiClient;
            $callbackUrl = route('api.external.invoice.callback');
            $external = $client->createInvoice(
                currency: 'usdt',
                network: 'tron',
                amount: $amount->toBeauty(),
                externalInvoiceId: (string) $invoice->id,
                callbackUrl: $callbackUrl,
                tag: null,
                metadata: null,
                productName: 'Пополнение баланса',
                productDescription: 'Пополнение баланса аккаунта трейдера',
                clientId: (string) $wallet->user_id,
            );

            // Сохраняем связку с внешним инвойсом
            $invoice->update([
                'external_id' => $external['id'] ?? null,
            ]);

            return [
                'invoice' => $invoice->fresh(),
                'external' => $external,
            ];
        });
    }

    /**
     * Завершить внешний депозит: меняем статус на SUCCESS и зачисляем средства
     */
    public function finishExternalDeposit(int $invoiceID, ?Money $amountReceived = null, ?string $txHash = null): Invoice
    {
        return Transaction::run(function () use ($invoiceID, $amountReceived, $txHash) {
            $invoice = Invoice::where('id', $invoiceID)->lockForUpdate()->first();

            if ($invoice->type->notEquals(InvoiceType::DEPOSIT)) {
                throw InvoiceException::invalidInvoiceType();
            }

            if ($invoice->status->notEquals(InvoiceStatus::PENDING)) {
                throw InvoiceException::invoiceAlreadyFinished();
            }

            $finalAmount = $amountReceived ?? $invoice->amount;

            $invoice->update([
                'status' => InvoiceStatus::SUCCESS,
                'tx_hash' => $txHash,
            ]);

            services()->wallet()
                ->giveToBalance(
                    walletID: $invoice->wallet->id,
                    amount: $finalAmount,
                    transactionType: TransactionType::DEPOSIT_BY_USER,
                    balanceType: $invoice->balance_type
                );

            return $invoice->fresh();
        });
    }

    /**
     * Отменить внешний депозит (истёк/отменён)
     */
    public function cancelExternalDeposit(int $invoiceID): Invoice
    {
        return Transaction::run(function () use ($invoiceID) {
            $invoice = Invoice::where('id', $invoiceID)->lockForUpdate()->first();

            if ($invoice->type->notEquals(InvoiceType::DEPOSIT)) {
                throw InvoiceException::invalidInvoiceType();
            }

            if ($invoice->status->notEquals(InvoiceStatus::PENDING)) {
                throw InvoiceException::invoiceAlreadyFinished();
            }

            $invoice->update(['status' => InvoiceStatus::FAIL]);

            return $invoice->fresh();
        });
    }
}
