<?php

namespace App\Http\Controllers\Trader;

use App\Enums\BalanceType;
use App\Exceptions\WalletDepositException;
use App\Http\Controllers\Controller;
use App\Http\Resources\WalletDepositInvoiceResource;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DepositInvoiceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $invoice = services()->walletDeposit()->createInvoice(
                walletID: $request->user()->wallet->id,
                amount: Money::fromPrecision((string) $validated['amount'], Currency::USDT()->getCode()),
                balanceType: BalanceType::TRUST,
            );

            return response()->json([
                'invoice' => (new WalletDepositInvoiceResource($invoice))->resolve(),
            ]);
        } catch (WalletDepositException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Trader deposit invoice creation failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Не удалось создать инвойс. Попробуйте позже.'], 500);
        }
    }
}
