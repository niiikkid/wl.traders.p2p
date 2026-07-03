<?php

namespace App\Http\Controllers;

use App\Enums\BalanceType;
use App\Exceptions\InvoiceException;
use App\Http\Requests\Invoice\StoreRequest;
use App\Models\Merchant;
use App\Models\WithdrawalAddress;
use App\Services\Money\Currency;
use App\Services\Money\Money;

class InvoiceController extends Controller
{
    public function store(StoreRequest $request)
    {
        try {
            $user = $request->user();
            $withdrawalAddress = WithdrawalAddress::query()
                ->where('user_id', $user->id)
                ->findOrFail($request->integer('withdrawal_address_id'));
            $balanceType = BalanceType::from($request->balance_type);
            $wallet = $user->wallet;

            if ($balanceType->equals(BalanceType::MERCHANT)) {
                $merchant = Merchant::query()
                    ->where('user_id', $user->id)
                    ->whereKey($request->integer('merchant_id'))
                    ->with('wallet')
                    ->firstOrFail();

                if (! $merchant->wallet) {
                    throw InvoiceException::merchantWalletMissing();
                }

                $wallet = $merchant->wallet;
            }

            services()->invoice()->createWithdrawal(
                walletID: $wallet->id,
                amount: Money::fromPrecision($request->amount, Currency::USDT()->getCode()),
                withdrawalAddress: $withdrawalAddress,
                balanceType: $balanceType,
            );

            return redirect()->back();
        } catch (InvoiceException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
