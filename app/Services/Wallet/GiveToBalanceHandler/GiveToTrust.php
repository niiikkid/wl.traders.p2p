<?php

namespace App\Services\Wallet\GiveToBalanceHandler;

use App\Enums\BalanceType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Exceptions\WalletException;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Money\Money;

class GiveToTrust extends GiveToBalance
{
    public function handle(Wallet $wallet, Money $amount, TransactionType $transactionType): void
    {
        if ($transactionType->direction()->notEquals(TransactionDirection::IN)) {
            throw WalletException::invalidTransactionTypeForGive();
        }

        $wallet->loadMissing('user');

        $maxReserveBalance = services()->wallet()->getMaxReserveBalance($wallet->user);
        $reserveNeeded = Money::fromPrecision((string) $maxReserveBalance, $wallet->reserve_balance->getCurrency());

        if ($wallet->reserve_balance->lessThan($reserveNeeded)) {
            $reserveDeficit = $reserveNeeded->sub($wallet->reserve_balance);

            if ($amount->lessOrEquals($reserveDeficit)) {
                // Все средства идут на резервный баланс
                $wallet->update([
                    'reserve_balance' => $wallet->reserve_balance->add($amount),
                ]);
            } else {
                // Часть на резерв, часть на доверительный баланс
                $trust = $amount->sub($reserveDeficit);
                $wallet->update([
                    'trust_balance' => $wallet->trust_balance->add($trust),
                    'reserve_balance' => $wallet->reserve_balance->add($reserveDeficit),
                ]);
            }
        } else {
            // Весь платеж идет на доверительный баланс
            $wallet->update([
                'trust_balance' => $wallet->trust_balance->add($amount),
            ]);
        }

        Transaction::create([
            'amount' => $amount,
            'direction' => TransactionDirection::IN,
            'type' => $transactionType,
            'balance_type' => BalanceType::TRUST,
            'wallet_id' => $wallet->id,
        ]);
    }
}
