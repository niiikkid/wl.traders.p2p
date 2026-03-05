<?php

namespace App\Http\Controllers\API\Merchant;

use App\Enums\BalanceType;
use App\Enums\NetworkEnum;
use App\Exceptions\InvoiceException;
use App\Http\Controllers\Controller;
use App\Rules\ValidateBaseUSDTAddress;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    public function balance()
    {
        $wallet = auth()->user()->wallet;

        return response()->success([
            'balance' => services()->wallet()->getTotalAvailableBalance($wallet, BalanceType::MERCHANT)->toBeauty(),
        ]);
    }

    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'address' => ['required', 'string', new ValidateBaseUSDTAddress($request->network)],
            'network' => ['required', 'string', Rule::enum(NetworkEnum::class)],
        ]);

        try {
            $invoice = services()->invoice()->createAutoWithdrawal(
                walletID: auth()->user()->wallet->id,
                amount: Money::fromPrecision($request->amount, Currency::USDT()),
                address: $request->address,
                network: NetworkEnum::from($request->network),
            );

            return response()->success([
                'invoice_id' => $invoice->id,
                'status' => $invoice->status->value,
            ]);
        } catch (InvoiceException $e) {
            return response()->failWithMessage($e->getMessage());
        }
    }
}
