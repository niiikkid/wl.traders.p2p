<?php

namespace App\Contracts;

use App\Services\Money\Money;

interface ProfitServiceContract
{
    /**
     * Логика: IN_BODY (вход + BODY) — комиссия "из тела".
     */
    public function calculateInBody(
        Money  $sourceAmount,
        Money  $exchangeRate,
        float  $totalFeeRate,
        float  $traderFeeRate,
        ?float $teamLeaderFeeRate = null,
        ?float $teamLeaderServiceSplitPercent = null
    ): object;

    /**
     * Выплаты: OUT_BODY с явным конвертом в USDT и распределением комиссий.
     */
    public function calculateOutBody(
        Money  $sourceAmount,
        Money  $exchangeRate,
        float  $totalFeeRate,
        float  $traderFeeRate,
        ?float $teamLeaderFeeRate = null,
        ?float $teamLeaderServiceSplitPercent = null
    ): object;
}
