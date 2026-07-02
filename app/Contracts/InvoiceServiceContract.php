<?php

namespace App\Contracts;

use App\Enums\BalanceType;
use App\Exceptions\InvoiceException;
use App\Models\Invoice;
use App\Models\WithdrawalAddress;
use App\Services\Money\Money;

interface InvoiceServiceContract
{
    /**
     * @throws InvoiceException
     */
    public function createWithdrawal(int $walletID, Money $amount, WithdrawalAddress $withdrawalAddress, BalanceType $balanceType): Invoice;

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
    public function deposit(int $walletID, Money $amount, BalanceType $balanceType, ?string $transactionID = null, ?string $txHash = null): void;

    /**
     * @throws InvoiceException
     */
    public function withdraw(int $walletID, Money $amount, BalanceType $balanceType): void;
}
