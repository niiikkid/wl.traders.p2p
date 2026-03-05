<?php

namespace App\Contracts;

use App\Enums\BalanceType;
use App\Enums\TransactionType;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Money\Money;
use App\Services\Wallet\ValueObjects\WalletStatsValue;

interface WalletServiceContract
{
    public function getMaxReserveBalance(User $user): int;

    public function create(User $user): Wallet;

    public function takeFromBalance(int $walletID, Money $amount, TransactionType $transactionType, BalanceType $balanceType): void;

    public function giveToBalance(int $walletID, Money $amount, TransactionType $transactionType, BalanceType $balanceType): void;

    public function getTotalAvailableBalance(Wallet $wallet, BalanceType $balanceType): Money;

    public function getWalletStats(Wallet $wallet): WalletStatsValue;
}
