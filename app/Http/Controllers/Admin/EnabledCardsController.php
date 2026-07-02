<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EnabledCardMinAmountLevel;
use App\Services\EnabledCards\EnabledCardsStatsService;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EnabledCardsController extends Controller
{
    public function __construct(
        private readonly EnabledCardsStatsService $statsService,
    ) {}

    public function storeLimitLevel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'string', Rule::in(Currency::getAllCodes())],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $currency = Currency::make($validated['currency']);
        $amountUnits = (int) Money::fromPrecision((string) $validated['amount'], $currency->getCode())->toUnits();

        if ($amountUnits <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Уровень лимита должен быть больше нуля.',
            ]);
        }

        EnabledCardMinAmountLevel::query()->firstOrCreate([
            'currency' => $currency->getCode(),
            'min_amount' => $amountUnits,
        ]);

        return $this->statisticsResponse($request);
    }

    public function destroyLimitLevel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'string', Rule::in(Currency::getAllCodes())],
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $currency = Currency::make($validated['currency']);

        EnabledCardMinAmountLevel::query()
            ->where('currency', $currency->getCode())
            ->where('min_amount', (int) $validated['amount'])
            ->delete();

        return $this->statisticsResponse($request);
    }

    private function statisticsResponse(Request $request): JsonResponse
    {
        $detailType = $request->input('detail_type');
        $paymentGatewayId = $request->input('payment_gateway_id');
        $userId = $request->input('user_id');

        return response()->json([
            'statistics' => $this->statsService->build($detailType, $paymentGatewayId, $userId),
            'filters' => [
                'detail_type' => $detailType,
                'payment_gateway_id' => $paymentGatewayId,
                'user_id' => $userId,
            ],
        ]);
    }
}
