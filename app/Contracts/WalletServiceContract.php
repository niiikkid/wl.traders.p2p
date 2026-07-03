<?php

namespace App\Contracts;

use App\Enums\BalanceType;
use App\Enums\TransactionType;
use App\Models\Merchant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Money\Money;
use App\Services\Wallet\ValueObjects\WalletStatsValue;
use Illuminate\Database\Eloquent\Model;

interface WalletServiceContract
{
    public function getMaxReserveBalance(User $user): int;

    public function create(User $user, ?Merchant $merchant = null): Wallet;

    public function createForMerchant(Merchant $merchant): Wallet;

    public function takeFromBalance(int $walletID, Money $amount, TransactionType $transactionType, BalanceType $balanceType, ?Model $transactionable = null): void;

    public function giveToBalance(int $walletID, Money $amount, TransactionType $transactionType, BalanceType $balanceType, ?Model $transactionable = null): void;

    public function getTotalAvailableBalance(Wallet $wallet, BalanceType $balanceType): Money;

    public function getWalletStats(Wallet $wallet): WalletStatsValue;

    public function getMerchantWalletStats(User $user, ?int $merchantID = null): array;

    public function getMerchantWalletSummaries(User $user): array;
}
