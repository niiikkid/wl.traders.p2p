<?php

namespace App\Services\Profit;

use App\Contracts\ProfitServiceContract;
use App\Services\Money\Currency;
use App\Services\Money\Money;

class ProfitService implements ProfitServiceContract
{
    /**
     * Логика: IN_BODY (вход + BODY) — комиссия "из тела".
     */
    public function calculateInBody(
        Money  $sourceAmount,                        // "Исходная сумма"
        Money  $exchangeRate,                        // "Курс обмена"
        float  $totalFeeRate,                 // "Суммарная комиссия, %"
        float  $traderFeeRate,                // "Комиссия трейдера, %"
        ?float $teamLeaderFeeRate = null,     // "Комиссия тимлида, %"
        ?float $teamLeaderServiceSplitPercent = null // "Доля тимлида за счет сервиса, %"
    ): object {
        $teamLeaderFeeRate = $teamLeaderFeeRate ?? 0.0;
        $this->validateRates($totalFeeRate, $traderFeeRate, $teamLeaderFeeRate);

        $convertedAmount = $this->convertToUsdt($sourceAmount, $exchangeRate);
        $totalFee = $convertedAmount->mul($totalFeeRate / 100);
        $traderCommission = $totalFeeRate > 0
            ? $totalFee->mul($traderFeeRate / $totalFeeRate)
            : Money::zero($totalFee->getCurrency()->getCode());
        $teamLeaderCommission = $totalFeeRate > 0 && $teamLeaderFeeRate > 0
            ? $totalFee->mul($teamLeaderFeeRate / $totalFeeRate)
            : Money::zero($totalFee->getCurrency()->getCode());
        $serviceCommission = $totalFee->sub($traderCommission)->abs();
        $teamLeaderCommissionFromService = $this->resolveTeamLeaderSplitFromService(
            splitPercent: $teamLeaderServiceSplitPercent,
            teamLeaderFee: $teamLeaderCommission
        );
        if ($teamLeaderFeeRate > 0 && $teamLeaderCommissionFromService === null) {
            throw new \InvalidArgumentException('Split source is required when team leader commission is set.');
        }

        [$traderFee, $teamLeaderFee, $serviceFee] = $this->applyTeamLeadSplitMoney(
            totalFee: $totalFee,
            traderFeeBase: $traderCommission,
            teamLeaderFee: $teamLeaderCommission,
            serviceFeeBase: $serviceCommission,
            teamLeaderSplitFromService: $teamLeaderCommissionFromService
        );

        $merchantCredit = $convertedAmount->sub($totalFee);
        $traderDebit = $convertedAmount->sub($traderFee);

        return (object) [
            'convertedAmount' => $convertedAmount,
            'totalFee' => $totalFee,
            'serviceFee' => $serviceFee,
            'traderFee' => $traderFee,
            'teamLeaderFee' => $teamLeaderFee,
            'merchantCredit' => $merchantCredit,
            'traderDebit' => $traderDebit,
        ];
    }

    /**
     * Выплаты: OUT_BODY с явным конвертом в USDT и распределением комиссий.
     */
    public function calculateOutBody(
        Money  $sourceAmount, // "Исходная сумма"
        Money  $exchangeRate, // "Курс обмена"
        float  $totalFeeRate, // "Суммарная комиссия, %"
        float  $traderFeeRate, // "Комиссия трейдера, %"
        ?float $teamLeaderFeeRate = null, // "Комиссия тимлида, %"
        ?float $teamLeaderServiceSplitPercent = null // "Доля тимлида за счет сервиса, %"
    ): object {

        $teamLeaderFeeRate = $teamLeaderFeeRate ?? 0.0;
        $this->validateRates($totalFeeRate, $traderFeeRate, $teamLeaderFeeRate);

        $convertedAmount = $this->convertToUsdt($sourceAmount, $exchangeRate);
        $totalFee = $convertedAmount->mul($this->rateFraction($totalFeeRate));
        $traderFeeBase = $totalFeeRate > 0
            ? $totalFee->mul($this->rateFraction($traderFeeRate, $totalFeeRate))
            : Money::zero(Currency::USDT()->getCode());
        $teamLeaderFee = $totalFeeRate > 0 && $teamLeaderFeeRate > 0
            ? $totalFee->mul($this->rateFraction($teamLeaderFeeRate, $totalFeeRate))
            : Money::zero(Currency::USDT()->getCode());
        $serviceFeeBase = $totalFee->sub($traderFeeBase)->abs();
        $teamLeaderSplitFromService = $this->resolveTeamLeaderSplitFromService(
            splitPercent: $teamLeaderServiceSplitPercent,
            teamLeaderFee: $teamLeaderFee
        );
        if ($teamLeaderFeeRate > 0 && $teamLeaderSplitFromService === null) {
            throw new \InvalidArgumentException('Split source is required when team leader commission is set.');
        }

        [$traderFee, $teamLeaderFee, $serviceFee] = $this->applyTeamLeadSplitMoney(
            totalFee: $totalFee,
            traderFeeBase: $traderFeeBase,
            teamLeaderFee: $teamLeaderFee,
            serviceFeeBase: $serviceFeeBase,
            teamLeaderSplitFromService: $teamLeaderSplitFromService
        );

        $merchantDebit = $convertedAmount->add($totalFee);
        $traderCredit = $convertedAmount->add($traderFee);

        return (object) [
            'convertedAmount' => $convertedAmount,
            'totalFee' => $totalFee,
            'serviceFee' => $serviceFee,
            'traderFee' => $traderFee,
            'teamLeaderFee' => $teamLeaderFee,
            'merchantDebit' => $merchantDebit,
            'traderCredit' => $traderCredit,
        ];
    }

    private function validateRates(float $totalCommissionRate, float $traderCommissionRate, float $teamLeaderCommissionRate): void
    {
        if ($totalCommissionRate < 0 || $traderCommissionRate < 0 || $teamLeaderCommissionRate < 0) {
            throw new \Exception('Commission rates must be non-negative.');
        }

        if ($totalCommissionRate < $traderCommissionRate) {
            throw new \Exception('The total commission cannot be less than trader commission.');
        }

        if ($teamLeaderCommissionRate > $totalCommissionRate) {
            throw new \Exception('The team leader commission cannot exceed total commission.');
        }
    }

    /**
     * Денежный сплит тимлида: часть платит сервис, остаток — трейдер.
     *
     * @return array{0: Money, 1: Money, 2: Money, 3: ?Money, 4: ?Money} traderFee, teamLeaderFee, serviceFee, tlFromService, tlFromTrader
     */
    private function applyTeamLeadSplitMoney(
        Money $totalFee,
        Money $traderFeeBase,
        Money $teamLeaderFee,
        Money $serviceFeeBase,
        ?Money $teamLeaderSplitFromService
    ): array {
        if ($teamLeaderSplitFromService === null) {
            if ($teamLeaderFee->greaterThanZero()) {
                throw new \InvalidArgumentException('Split source is required when team leader commission is set.');
            }
            return [$traderFeeBase, $teamLeaderFee, $serviceFeeBase, null, null];
        }

        if ($teamLeaderSplitFromService->getCurrency()->notEquals($totalFee->getCurrency())) {
            throw new \InvalidArgumentException('Team leader split currency must match total fee currency.');
        }

        if ($teamLeaderSplitFromService->lessThanZero()) {
            throw new \InvalidArgumentException('Team leader split from service must be non-negative.');
        }

        if ($teamLeaderSplitFromService->greaterThan($teamLeaderFee)) {
            throw new \InvalidArgumentException('Team leader split from service cannot exceed team leader fee.');
        }

        if ($teamLeaderSplitFromService->greaterThan($serviceFeeBase)) {
            throw new \InvalidArgumentException('Team leader split from service cannot exceed service fee.');
        }

        $teamLeaderSplitFromTrader = $teamLeaderFee->sub($teamLeaderSplitFromService);

        if ($teamLeaderSplitFromTrader->greaterThan($traderFeeBase)) {
            throw new \InvalidArgumentException('Team leader split from trader cannot exceed trader fee.');
        }

        $traderFee = $traderFeeBase->sub($teamLeaderSplitFromTrader);
        $serviceFee = $serviceFeeBase->sub($teamLeaderSplitFromService);

        return [$traderFee, $teamLeaderFee, $serviceFee, $teamLeaderSplitFromService, $teamLeaderSplitFromTrader];
    }

    private function resolveTeamLeaderSplitFromService(?float $splitPercent, Money $teamLeaderFee): ?Money
    {
        if ($splitPercent === null) {
            return null;
        }

        return $teamLeaderFee->mul($this->rateFraction($splitPercent));
    }

    private function convertToUsdt(Money $amountFiat, Money $conversionPrice): Money
    {
        if ($amountFiat->getCurrency()->notEquals($conversionPrice->getCurrency())) {
            throw new \InvalidArgumentException('Conversion currencies must match.');
        }

        $usdtAmount = bcdiv(
            $amountFiat->toPrecision(),
            $conversionPrice->toPrecision(),
            Money::DEFAULT_PRECISION
        );

        return Money::fromPrecision($usdtAmount, Currency::USDT()->getCode());
    }

    private function rateFraction(float $value, float $divider = 100): string
    {
        if ($divider === 0.0) {
            return '0';
        }

        $fraction = bcdiv((string) $value, (string) $divider, 10);

        return rtrim(rtrim($fraction, '0'), '.') ?: '0';
    }
}
