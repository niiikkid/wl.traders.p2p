<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V2;

use App\Enums\BalanceType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class WalletController extends Controller
{
    public function balance(): JsonResponse
    {
        $wallet = auth()->user()->wallet;

        return response()->success([
            'balance' => services()->wallet()->getTotalAvailableBalance($wallet, BalanceType::MERCHANT)->toBeauty(),
        ]);
    }
}
