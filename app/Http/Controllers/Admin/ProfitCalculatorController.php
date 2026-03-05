<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Contracts\ProfitServiceContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Profit\CalculateProfitRequest;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProfitCalculatorController extends Controller
{
    /**
     * @return Response
     */
    public function index(): Response
    {
        $currencies = Currency::getAllCodes();
        $defaultCurrency = $currencies[0] ?? 'rub';

        return Inertia::render('Admin/Profit/Index', [
            'currencies' => $currencies,
            'defaults' => [
                'logic' => 'in_body',
                'amount_currency' => $defaultCurrency,
                'amount' => '1000',
                'exchange_rate' => '100',
                'total_commission_rate' => 5,
                'trader_commission_rate' => 2,
                'teamleader_commission_rate' => 0,
            ],
        ]);
    }

    /**
     * @param CalculateProfitRequest $request
     * @param ProfitServiceContract $profitService
     *
     * @return JsonResponse
     */
    public function calculate(
        CalculateProfitRequest $request,
        ProfitServiceContract $profitService
    ): JsonResponse {
        $validated = $request->validated();

        $logic = $validated['logic'];
        $amountCurrency = $validated['amount_currency'];
        $amount = Money::fromPrecision((string) $validated['amount'], $amountCurrency);
        $exchangeRate = Money::fromPrecision((string) $validated['exchange_rate'], $amountCurrency);
        $totalCommissionRate = (float) $validated['total_commission_rate'];
        $traderCommissionRate = (float) $validated['trader_commission_rate'];
        $teamLeaderCommissionRate = (float) $validated['teamleader_commission_rate'];
        $teamLeaderSplitFromServicePercent = $validated['teamleader_split_from_service_percent'] ?? null;

        try {
            $profits = match ($logic) {
                'in_body' => $profitService->calculateInBody(
                    sourceAmount: $amount,
                    exchangeRate: $exchangeRate,
                    totalFeeRate: $totalCommissionRate,
                    traderFeeRate: $traderCommissionRate,
                    teamLeaderFeeRate: $teamLeaderCommissionRate,
                    teamLeaderServiceSplitPercent: $teamLeaderSplitFromServicePercent
                ),
                'out_body' => $profitService->calculateOutBody(
                    sourceAmount: $amount,
                    exchangeRate: $exchangeRate,
                    totalFeeRate: $totalCommissionRate,
                    traderFeeRate: $traderCommissionRate,
                    teamLeaderFeeRate: $teamLeaderCommissionRate,
                    teamLeaderServiceSplitPercent: $teamLeaderSplitFromServicePercent
                ),
            };
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $convertedAmount = $this->getCalcMoney($profits, 'convertedAmount');
        $totalFee = $this->getCalcMoney($profits, 'totalFee');
        $serviceFee = $this->getCalcMoney($profits, 'serviceFee');
        $traderFee = $this->getCalcMoney($profits, 'traderFee');
        $teamLeaderFee = $this->getCalcMoney($profits, 'teamLeaderFee');
        $merchantCredit = $this->getCalcMoney($profits, 'merchantCredit');
        $merchantDebit = $this->getCalcMoney($profits, 'merchantDebit');
        $traderDebit = $this->getCalcMoney($profits, 'traderDebit');
        $traderCredit = $this->getCalcMoney($profits, 'traderCredit');

        return response()->json([
            'success' => true,
            'data' => [
                'outputs' => [
                    'convertedAmount' => $this->formatMoney($convertedAmount),
                    'totalFee' => $this->formatMoney($totalFee),
                    'serviceFee' => $this->formatMoney($serviceFee),
                    'traderFee' => $this->formatMoney($traderFee),
                    'teamLeaderFee' => $this->formatMoney($teamLeaderFee),
                    'merchantCredit' => $this->formatMoney($merchantCredit),
                    'merchantDebit' => $this->formatMoney($merchantDebit),
                    'traderDebit' => $this->formatMoney($traderDebit),
                    'traderCredit' => $this->formatMoney($traderCredit),
                ],
            ],
        ]);
    }

    private function getCalcMoney(object $calc, string $property): ?Money
    {
        return property_exists($calc, $property) ? $calc->{$property} : null;
    }

    /**
     * @return array{value: string, currency: string}|null
     */
    private function formatMoney(?Money $money): ?array
    {
        if (! $money) {
            return null;
        }

        return [
            'value' => $money->toPrecision(),
            'currency' => strtoupper($money->getCurrency()->getCode()),
        ];
    }
}
