<?php

namespace App\Http\Controllers\API\Deposit;

use App\Enums\BalanceType;
use App\Exceptions\InvoiceException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Http\Request;

class DepositController extends Controller
{
    public function webhook(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'max:255', 'exists:users,email'],
            'amount' => ['required', 'numeric', 'min:1'],
            'transaction_id' => ['required', 'string'],
            'tx_hash' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = User::query()->with('wallet')->where('email', $request->email)->first();

        if (! $user?->wallet) {
            return response()->failWithMessage('Кошелёк пользователя не найден.', 422);
        }

        try {
            services()->invoice()->deposit(
                walletID: $user->wallet->id,
                amount: Money::fromPrecision($request->amount, Currency::USDT()),
                balanceType: BalanceType::TRUST,
                transactionID: $request->transaction_id,
                txHash: $request->tx_hash,
            );

            return response()->success();
        } catch (InvoiceException $e) {
            return response()->failWithMessage($e->getMessage());
        }
    }
}
