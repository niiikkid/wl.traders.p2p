<?php

namespace App\Http\Controllers\API\Merchant;

use App\Enums\BalanceType;
use App\Http\Controllers\Controller;

class WalletController extends Controller
{
    public function balance()
    {
        $wallet = auth()->user()->wallet;

        return response()->success([
            'balance' => services()->wallet()->getTotalAvailableBalance($wallet, BalanceType::MERCHANT)->toBeauty(),
        ]);
    }
}
