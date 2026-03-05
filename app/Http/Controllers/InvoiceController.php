<?php

namespace App\Http\Controllers;

use App\Enums\BalanceType;
use App\Exceptions\InvoiceException;
use App\Http\Requests\Invoice\StoreRequest;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Support\Facades\Http;

class InvoiceController extends Controller
{
    public function store(StoreRequest $request)
    {
        try {
            services()->invoice()->createWithdrawal(
                walletID: auth()->user()->wallet->id,
                amount: Money::fromPrecision($request->amount, Currency::USDT()),
                address: $request->address,
                balanceType: BalanceType::from($request->balance_type),
            );

            return redirect()->back();
        } catch (InvoiceException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
