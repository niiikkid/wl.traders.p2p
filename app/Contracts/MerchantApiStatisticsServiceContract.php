<?php

namespace App\Contracts;

use Carbon\Carbon;

interface MerchantApiStatisticsServiceContract
{
    /**
     * Обновляет статистику за указанный период
     */
    public function updateStatistics(Carbon $fromDate, Carbon $toDate): void;

    /**
     * Обновляет статистику за сегодня и вчера (для учета последних изменений)
     */
    public function updateTodayStatistics(): void;

    /**
     * Получает статистику за сегодня и за все время
     */
    public function getStatistics(): array;

    /**
     * @return array{
     *     success_count: int,
     *     failed_count: int,
     *     total_count: int,
     *     processing_rate: float,
     *     processing_rate_formatted: string
     * }
     */
    public function getOrderCreateRequestsStats(Carbon $startDate, Carbon $endDate): array;
}
