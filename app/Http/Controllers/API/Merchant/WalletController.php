<?php

namespace App\Http\Controllers\API\Merchant;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Money\Currency;
use App\Services\Money\Money;

class WalletController extends Controller
{
    public function balance()
    {
        /** @var User $user */
        $user = auth()->user();
        $merchantWallets = services()->wallet()->getMerchantWalletSummaries($user);
        $totalBalance = Money::fromUnits(
            (string) Wallet::query()
                ->where('user_id', $user->id)
                ->whereNotNull('merchant_id')
                ->sum('merchant_balance'),
            Currency::USDT()
        );

        return response()->success([
            'balance' => $totalBalance->toBeauty(),
            'merchants' => collect($merchantWallets)
                ->map(fn (array $merchantWallet): array => [
                    'uuid' => $merchantWallet['uuid'],
                    'name' => $merchantWallet['name'],
                    'balance' => $merchantWallet['balance'],
                    'currency' => $merchantWallet['currency'],
                ])
                ->values()
                ->all(),
        ]);
    }
}
