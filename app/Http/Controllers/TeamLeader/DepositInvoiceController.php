<?php

declare(strict_types=1);

namespace App\Http\Controllers\TeamLeader;

use App\Enums\BalanceType;
use App\Exceptions\WalletDepositException;
use App\Http\Controllers\Controller;
use App\Http\Resources\WalletDepositInvoiceResource;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Services\User\TeamLeaderInsuranceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DepositInvoiceController extends Controller
{
    public function __construct(
        private readonly TeamLeaderInsuranceService $teamLeaderInsuranceService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->teamLeaderInsuranceService->teamLeaderUsesSharedReserve($user)) {
            return response()->json([
                'message' => 'Пополнение общего резерва недоступно в текущем режиме Team Leader.',
            ], 422);
        }

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $invoice = services()->walletDeposit()->createInvoice(
                walletID: $user->wallet->id,
                amount: Money::fromPrecision((string) $validated['amount'], Currency::USDT()->getCode()),
                balanceType: BalanceType::RESERVE,
            );

            return response()->json([
                'invoice' => (new WalletDepositInvoiceResource($invoice))->resolve(),
            ]);
        } catch (WalletDepositException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Team Leader reserve deposit invoice creation failed', [
                'user_id' => $user?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Не удалось создать инвойс. Попробуйте позже.'], 500);
        }
    }
}
