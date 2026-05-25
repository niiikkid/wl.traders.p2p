<?php

declare(strict_types=1);

namespace App\Http\Controllers\TeamLeader;

use App\Enums\BalanceType;
use App\Exceptions\InvoiceException;
use App\Http\Controllers\Controller;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use App\Services\User\TeamLeaderInsuranceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        try {
            $result = services()->invoice()->createExternalDeposit(
                walletID: $user->wallet->id,
                amount: Money::fromPrecision((string) $validated['amount'], Currency::USDT()->getCode()),
                balanceType: BalanceType::RESERVE,
            );

            $external = $result['external'] ?? [];

            return response()->json([
                'payment_url' => $external['payment_url'] ?? null,
                'external_invoice_id' => $external['id'] ?? null,
                'invoice_id' => $result['invoice']->id ?? null,
            ]);
        } catch (InvoiceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (HttpExceptionInterface $e) {
            Log::warning('Team Leader reserve deposit invoice creation failed', [
                'user_id' => $user->id,
                'amount' => $validated['amount'] ?? null,
                'status' => $e->getStatusCode(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage() ?: 'Ошибка внешнего сервиса при создании инвойса',
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('Team Leader reserve deposit invoice creation unexpected error', [
                'user_id' => $user->id,
                'amount' => $validated['amount'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Не удалось создать инвойс. Попробуйте позже.',
            ], 500);
        }
    }
}
