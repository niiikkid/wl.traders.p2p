<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Services\Money\Currency;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class AmountDistributionBucketService
{
    /**
     * @var list<array{value: string, label: string}>
     */
    public const array PERIOD_OPTIONS = [
        ['value' => '1d', 'label' => 'За день'],
        ['value' => '7d', 'label' => 'За 7 дней'],
        ['value' => '14d', 'label' => 'За 2 недели'],
        ['value' => '30d', 'label' => 'За 30 дней'],
        ['value' => 'current_month', 'label' => 'За текущий месяц'],
    ];

    /**
     * Upper bounds for deal amount buckets in display currency amounts.
     *
     * @var array<string, list<int>>
     */
    private const array BUCKET_MAX_BY_CURRENCY = [
        'rub' => [1000, 2000, 3000, 5000, 10000, 15000, 20000, 30000, 50000, 100000],
        'kzt' => [5000, 10000, 15000, 20000, 30000, 50000, 100000, 200000, 500000],
        'uah' => [300, 500, 800, 1000, 1500, 2000, 3000, 5000, 10000, 20000, 50000],
        'eur' => [10, 25, 50, 100, 200, 500, 1000, 2000, 5000],
        'usd' => [10, 25, 50, 100, 200, 500, 1000, 2000, 5000],
        'tjs' => [100, 200, 500, 1000, 2000, 5000, 10000],
        'kgs' => [500, 1000, 2000, 5000, 10000, 20000, 50000],
        'azn' => [20, 50, 100, 200, 500, 1000, 2000],
        'try' => [500, 1000, 2000, 3000, 5000, 10000, 20000, 50000],
        'idr' => [100000, 200000, 500000, 1000000, 2000000, 5000000, 10000000],
        'pln' => [20, 50, 100, 200, 500, 1000, 2000, 5000],
    ];

    /** @var array<string, list<array{key: string, label: string, max: float|null}>>|null */
    private static ?array $bucketsCache = null;

    public function normalizePeriod(?string $period): string
    {
        $period = trim((string) ($period ?? ''));

        foreach (self::PERIOD_OPTIONS as $option) {
            if ($option['value'] === $period) {
                return $period;
            }
        }

        return 'current_month';
    }

    public function resolvePeriodBounds(
        ?string $period,
        ?string $dateFrom,
        ?string $dateTo,
    ): array {
        $customStartAt = $this->parseDateBoundary($dateFrom, startOfDay: true);
        $customEndAt = $this->parseDateBoundary($dateTo, startOfDay: false);

        if ($customStartAt !== null || $customEndAt !== null) {
            return [$customStartAt, $customEndAt];
        }

        return match ($period) {
            '1d' => [now()->subDay(), null],
            '7d' => [now()->subDays(7), null],
            '14d' => [now()->subDays(14), null],
            '30d' => [now()->subDays(30), null],
            'current_month' => [now()->startOfMonth(), now()->endOfMonth()],
            default => [null, null],
        };
    }

    public function bucketCaseSqlForFiatAmount(string $currencyCode, string $amountExpression): string
    {
        $conditions = [];

        foreach ($this->bucketsForCurrency($currencyCode) as $bucketDefinition) {
            if ($bucketDefinition['max'] === null) {
                continue;
            }

            $maxFiat = $bucketDefinition['max'];
            $bucketKey = $bucketDefinition['key'];
            $conditions[] = "WHEN {$amountExpression} < {$maxFiat} THEN '{$bucketKey}'";
        }

        $buckets = $this->bucketsForCurrency($currencyCode);
        $lastBucketKey = $buckets[array_key_last($buckets)]['key'];

        return 'CASE '.implode(' ', $conditions)." ELSE '{$lastBucketKey}' END";
    }

    /**
     * @return array{buckets: list<array{key: string, label: string, count: int, percent: float}>, total_deals: int}
     */
    public function formatDistributionRows(iterable $rows, string $currencyCode): array
    {
        $countsByKey = [];

        foreach ($rows as $row) {
            $bucketKey = (string) ($row->amount_bucket ?? '');
            $countsByKey[$bucketKey] = ($countsByKey[$bucketKey] ?? 0) + (int) ($row->deals_count ?? 0);
        }

        $buckets = [];
        $totalDeals = 0;

        foreach ($this->bucketsForCurrency($currencyCode) as $bucketDefinition) {
            $count = $countsByKey[$bucketDefinition['key']] ?? 0;

            $buckets[] = [
                'key' => $bucketDefinition['key'],
                'label' => $bucketDefinition['label'],
                'count' => $count,
                'percent' => 0.0,
            ];
            $totalDeals += $count;
        }

        if ($totalDeals > 0) {
            $buckets = array_map(
                function (array $bucket) use ($totalDeals): array {
                    $bucket['percent'] = round(($bucket['count'] / $totalDeals) * 100, 1);

                    return $bucket;
                },
                $buckets,
            );
        }

        return [
            'buckets' => $buckets,
            'total_deals' => $totalDeals,
        ];
    }

    /**
     * @return list<array{key: string, label: string, max: float|null}>
     */
    private function bucketsForCurrency(string $currencyCode): array
    {
        $currencyCode = strtolower($currencyCode);

        if (self::$bucketsCache === null) {
            self::$bucketsCache = [];

            foreach (Currency::getAll() as $currency) {
                self::$bucketsCache[$currency->getCode()] = $this->buildBucketsForCurrency($currency);
            }
        }

        return self::$bucketsCache[$currencyCode]
            ?? $this->buildBucketsForCurrency(Currency::make($currencyCode));
    }

    /**
     * @return list<array{key: string, label: string, max: float|null}>
     */
    private function buildBucketsForCurrency(Currency $currency): array
    {
        $currencyCode = $currency->getCode();
        $maxBoundaries = self::BUCKET_MAX_BY_CURRENCY[$currencyCode] ?? [1000, 5000, 10000, 50000];
        $previousMax = 0.0;
        $buckets = [];

        foreach ($maxBoundaries as $maxFiat) {
            $maxFiat = (float) $maxFiat;
            $minFiat = (int) $previousMax;
            $maxFiatInt = (int) $maxFiat;

            $buckets[] = [
                'key' => "{$currencyCode}_{$minFiat}_{$maxFiatInt}",
                'label' => $this->formatBucketLabel($previousMax, $maxFiat, $currency),
                'max' => $maxFiat,
            ];
            $previousMax = $maxFiat;
        }

        $lastMin = (int) $previousMax;

        $buckets[] = [
            'key' => "{$currencyCode}_{$lastMin}_plus",
            'label' => 'от '.$this->formatDisplayAmount($previousMax, $currency).' '.$currency->getSymbol(),
            'max' => null,
        ];

        return $buckets;
    }

    private function formatBucketLabel(float $min, float $max, Currency $currency): string
    {
        $symbol = $currency->getSymbol();

        if ($min <= 0) {
            return '0–'.$this->formatDisplayAmount($max, $currency).' '.$symbol;
        }

        return $this->formatDisplayAmount($min, $currency)
            .'–'
            .$this->formatDisplayAmount($max, $currency)
            .' '
            .$symbol;
    }

    private function formatDisplayAmount(float $amount, Currency $currency): string
    {
        if (fmod($amount, 1.0) === 0.0) {
            return number_format((int) round($amount), 0, '', ' ');
        }

        $displayPrecision = $currency->getDisplayPrecision();

        return rtrim(
            rtrim(number_format($amount, $displayPrecision, ',', ' '), '0'),
            ',',
        );
    }

    private function parseDateBoundary(?string $value, bool $startOfDay): ?CarbonInterface
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $date = Carbon::parse($value);

        return $startOfDay ? $date->startOfDay() : $date->endOfDay();
    }
}
