<?php

declare(strict_types=1);

/**
 * Каскад: синхронизация баланса мерчанта со статусом сделки.
 */

namespace App\Services\Cascade;

use App\Enums\BalanceType;
use App\Enums\CascadeDealStatus;
use App\Enums\ProviderType;
use App\Enums\TransactionType;
use App\Exceptions\CascadeException;
use App\Models\CascadeDeal;
use App\Models\CascadeProvider;
use App\Models\Wallet;
use App\Services\Money\Money;

/**
 * Зачисляет и возвращает кредит на кошелёк мерчанта при переходах в/из SUCCESS для внешних провайдеров без привязки к заказу.
 */
class CascadeMerchantBalanceService
{
    public function syncForStatusTransition(
        CascadeDeal $deal,
        CascadeProvider $provider,
        ?CascadeDealStatus $fromStatus,
        ?CascadeDealStatus $toStatus,
    ): void {
        if (! $provider->provider_type->equals(ProviderType::EXTERNAL) || $deal->order_id !== null) {
            return;
        }

        $amount = $deal->credit;

        if (! $amount instanceof Money || ! $amount->greaterThanZero()) {
            return;
        }

        $wallet = $this->merchantWallet($deal);

        if (($fromStatus?->equals(CascadeDealStatus::SUCCESS) ?? false) === false && $toStatus?->equals(CascadeDealStatus::SUCCESS)) {
            services()->wallet()->giveToBalance(
                walletID: $wallet->id,
                amount: $amount,
                transactionType: TransactionType::from('income_from_a_successful_cascade_deal'),
                balanceType: BalanceType::MERCHANT,
                transactionable: $deal,
            );

            return;
        }

        if (($fromStatus?->equals(CascadeDealStatus::SUCCESS) ?? false) && ($toStatus?->equals(CascadeDealStatus::SUCCESS) ?? false) === false) {
            services()->wallet()->takeFromBalance(
                walletID: $wallet->id,
                amount: $amount,
                transactionType: TransactionType::from('rollback_income_from_a_successful_cascade_deal'),
                balanceType: BalanceType::MERCHANT,
                transactionable: $deal,
            );
        }
    }

    private function merchantWallet(CascadeDeal $deal): Wallet
    {
        $deal->loadMissing('merchant.user.wallet');
        $merchant = $deal->merchant;

        if (! $merchant?->user) {
            throw CascadeException::make('У мерчанта каскадной сделки отсутствует пользователь.');
        }

        if (! $merchant->user->wallet) {
            $wallet = services()->wallet()->create($merchant->user);
            $merchant->user->setRelation('wallet', $wallet);
        }

        return $merchant->user->wallet;
    }
}
