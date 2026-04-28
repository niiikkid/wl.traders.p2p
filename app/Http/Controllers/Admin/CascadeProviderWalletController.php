<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BalanceType;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\CascadeProvider;
use App\Models\FundsOnHold;
use App\Services\Cascade\CascadeProviderCollateralService;
use App\Services\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CascadeProviderWalletController extends Controller
{
    public function deposit(Request $request, CascadeProvider $cascadeProvider): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.00000001'],
        ]);

        $wallet = $this->providerWallet($cascadeProvider);

        services()->wallet()->giveToBalance(
            walletID: $wallet->id,
            amount: Money::fromPrecision((string) $data['amount'], 'USDT'),
            transactionType: TransactionType::CASCADE_PROVIDER_ADMIN_DEPOSIT,
            balanceType: BalanceType::PROVIDER,
        );

        return back();
    }

    public function withdraw(Request $request, CascadeProvider $cascadeProvider): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.00000001'],
        ]);

        $wallet = $this->providerWallet($cascadeProvider);

        services()->wallet()->takeFromBalance(
            walletID: $wallet->id,
            amount: Money::fromPrecision((string) $data['amount'], 'USDT'),
            transactionType: TransactionType::CASCADE_PROVIDER_ADMIN_WITHDRAWAL,
            balanceType: BalanceType::PROVIDER,
        );

        return back();
    }

    public function releaseHold(FundsOnHold $fundsOnHold, CascadeProviderCollateralService $collateral): RedirectResponse
    {
        $collateral->release($fundsOnHold);

        return back();
    }

    public function reconcileHold(FundsOnHold $fundsOnHold, CascadeProviderCollateralService $collateral): RedirectResponse
    {
        $collateral->markReconciled($fundsOnHold);

        return back();
    }

    private function providerWallet(CascadeProvider $provider)
    {
        $provider->loadMissing('user.wallet');

        if (! $provider->user) {
            abort(422, 'У провайдера не привязан пользователь Provider Liquidity.');
        }

        if (! $provider->user->wallet) {
            services()->wallet()->create($provider->user);
            $provider->user->refresh()->load('wallet');
        }

        return $provider->user->wallet;
    }
}
