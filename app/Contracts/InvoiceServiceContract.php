<?php

namespace App\Contracts;

use App\Enums\BalanceType;
use App\Enums\NetworkEnum;
use App\Exceptions\InvoiceException;
use App\Models\Invoice;
use App\Models\Wallet;
use App\Models\User;
use App\Services\Money\Money;

interface InvoiceServiceContract
{
    /**
     * @throws InvoiceException
     */
    public function createWithdrawal(int $walletID, Money $amount, ?string $address, BalanceType $balanceType): Invoice;

    /**
     * @throws InvoiceException
     */
    public function createAutoWithdrawal(int $walletID, Money $amount, string $address, NetworkEnum $network): Invoice;

    /**
     * @throws InvoiceException
     */
    public function finishAutoWithdrawal(int $paymentID, string $status, ?string $txHash = null): Invoice;

    /**
     * @throws InvoiceException
     */
    public function finishWithdrawal(int $invoiceID): void;

    /**
     * @throws InvoiceException
     */
    public function cancelWithdrawal(int $invoiceID): void;

    /**
     * @throws InvoiceException
     */
    public function deposit(int $walletID, Money $amount, BalanceType $balanceType, string $transactionID = null, string $txHash = null): void;

    /**
     * @throws InvoiceException
     */
    public function withdraw(int $walletID, Money $amount, BalanceType $balanceType): void;

    /**
     * Внешний депозит: создание локального PENDING + внешнего инвойса
     */
    public function createExternalDeposit(int $walletID, Money $amount, BalanceType $balanceType): array;

    /**
     * Завершить внешний депозит (paid)
     */
    public function finishExternalDeposit(int $invoiceID, ?Money $amountReceived = null, ?string $txHash = null): Invoice;

    /**
     * Отменить внешний депозит (expired/cancelled)
     */
    public function cancelExternalDeposit(int $invoiceID): Invoice;

    /**
     * Возвращает массив доступных сетей для вывода средств в зависимости от роли пользователя
     */
    public function getAvailableNetworks(User $user): array;
}
