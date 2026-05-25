<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Enums\BalanceType;
use App\Enums\TransactionType;
use App\Exceptions\WalletException;
use App\Models\Order;
use App\Models\User;
use App\Services\Money\Money;
use App\Services\Order\ValueObjects\OrderDebitAllocation;

class OrderTraderDebitService
{
    public function getAvailableDebitBalance(User $trader): Money
    {
        $trader->loadMissing(['wallet', 'teamLeader.wallet']);

        if (! $trader->usesTeamLeaderSharedReserve()) {
            return services()->wallet()->getTotalAvailableBalance($trader->wallet, BalanceType::TRUST);
        }

        $teamLeader = $trader->teamLeader;
        if ($teamLeader?->wallet === null) {
            return $trader->wallet->trust_balance;
        }

        return $trader->wallet->trust_balance->add($teamLeader->wallet->reserve_balance);
    }

    public function debit(User $trader, Money $amount, Order $order, TransactionType $transactionType): ?OrderDebitAllocation
    {
        if (! $amount->greaterThanZero()) {
            return null;
        }

        $trader->loadMissing(['wallet', 'teamLeader.wallet']);

        if (! $trader->usesTeamLeaderSharedReserve()) {
            services()->wallet()->takeFromBalance(
                $trader->wallet->id,
                $amount,
                $transactionType,
                BalanceType::TRUST,
                $order,
            );

            return null;
        }

        $allocation = $this->resolveSharedReserveAllocation($trader, $amount);

        if ($allocation->traderTrust->greaterThanZero()) {
            services()->wallet()->takeFromBalance(
                $trader->wallet->id,
                $allocation->traderTrust,
                $transactionType,
                BalanceType::TRUST,
                $order,
            );
        }

        if ($allocation->teamLeaderReserve->greaterThanZero()) {
            $teamLeaderWallet = $trader->teamLeader?->wallet;
            if ($teamLeaderWallet === null) {
                throw WalletException::insufficientFunds();
            }

            services()->wallet()->takeFromBalance(
                $teamLeaderWallet->id,
                $allocation->teamLeaderReserve,
                $transactionType,
                BalanceType::RESERVE,
                $order,
            );
        }

        return $allocation;
    }

    public function refund(Order $order, TransactionType $transactionType): void
    {
        if (! $this->hasAllocationSnapshot($order)) {
            services()->wallet()->giveToBalance(
                $order->trader->wallet->id,
                $order->trader_paid_for_order,
                $transactionType,
                BalanceType::TRUST,
                $order,
            );

            return;
        }

        $order->loadMissing(['trader.wallet', 'teamLeader.wallet']);

        if ($order->trader_trust_paid_for_order->greaterThanZero()) {
            services()->wallet()->giveToBalance(
                $order->trader->wallet->id,
                $order->trader_trust_paid_for_order,
                $transactionType,
                BalanceType::TRUST,
                $order,
            );
        }

        if ($order->team_leader_reserve_paid_for_order->greaterThanZero()) {
            $teamLeaderWallet = $order->teamLeader?->wallet;
            if ($teamLeaderWallet === null) {
                throw WalletException::insufficientFunds();
            }

            services()->wallet()->giveToBalance(
                $teamLeaderWallet->id,
                $order->team_leader_reserve_paid_for_order,
                $transactionType,
                BalanceType::RESERVE,
                $order,
            );
        }
    }

    public function hasAllocationSnapshot(Order $order): bool
    {
        return $order->trader_trust_paid_for_order !== null;
    }

    private function resolveSharedReserveAllocation(User $trader, Money $amount): OrderDebitAllocation
    {
        $trustBalance = $trader->wallet->trust_balance;
        $traderTrustDebit = $trustBalance->greaterOrEquals($amount)
            ? $amount
            : ($trustBalance->greaterThanZero()
                ? $trustBalance
                : Money::fromUnits('0', $amount->getCurrency()->getCode()));

        $teamLeaderReserveDebit = $amount->sub($traderTrustDebit);
        $teamLeaderWallet = $trader->teamLeader?->wallet;

        if ($teamLeaderReserveDebit->greaterThanZero()) {
            if ($teamLeaderWallet === null || $teamLeaderWallet->reserve_balance->lessThan($teamLeaderReserveDebit)) {
                throw WalletException::insufficientFunds();
            }
        }

        return new OrderDebitAllocation($traderTrustDebit, $teamLeaderReserveDebit);
    }
}
