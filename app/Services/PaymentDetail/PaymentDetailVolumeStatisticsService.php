<?php

declare(strict_types=1);

namespace App\Services\PaymentDetail;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PaymentDetail;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class PaymentDetailVolumeStatisticsService
{
    private const int MODAL_STATISTICS_CACHE_TTL_SECONDS = 60;

    /**
     * @var list<array{value: string, label: string}>
     */
    public const array PERIOD_OPTIONS = [
        ['value' => '1d', 'label' => 'За день'],
        ['value' => '7d', 'label' => 'За 7 дней'],
        ['value' => '14d', 'label' => 'За 2 недели'],
        ['value' => '30d', 'label' => 'За 30 дней'],
        ['value' => 'current_month', 'label' => 'За текущий месяц'],
        ['value' => 'all', 'label' => 'За всё время'],
    ];

    /**
     * Upper bounds for deal amount buckets in display currency amounts.
     *
     * @var array<string, list<int>>
     */
    private const array MODAL_DEAL_AMOUNT_BUCKET_MAX_BY_CURRENCY = [
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

    /** @var array<string, list<array{key: string, label: string, max: int|null}>>|null */
    private static ?array $modalDealAmountBucketsCache = null;

    /**
     * @return array{
     *     period: string,
     *     period_options: list<array{value: string, label: string}>,
     *     payment_detail: array<string, mixed>,
     *     volume: string,
     *     deals_count: int,
     *     distribution: array{buckets: list<array{key: string, label: string, count: int, percent: float}>, total_deals: int}
     * }
     */
    public function buildModalPayload(PaymentDetail $paymentDetail, string $period): array
    {
        $period = $this->normalizePeriod($period);

        return Cache::remember(
            $this->modalStatisticsCacheKey($paymentDetail->id, $period),
            now()->addSeconds(self::MODAL_STATISTICS_CACHE_TTL_SECONDS),
            fn (): array => $this->calculateModalPayload($paymentDetail, $period),
        );
    }

    private function normalizePeriod(?string $period): string
    {
        $period = trim((string) ($period ?? ''));

        foreach (self::PERIOD_OPTIONS as $option) {
            if ($option['value'] === $period) {
                return $period;
            }
        }

        return 'current_month';
    }

    private function modalStatisticsCacheKey(int $paymentDetailId, string $period): string
    {
        return "payment_detail_volume_statistics:{$paymentDetailId}:{$period}";
    }

    /**
     * @return array{
     *     period: string,
     *     period_options: list<array{value: string, label: string}>,
     *     payment_detail: array<string, mixed>,
     *     volume: string,
     *     deals_count: int,
     *     distribution: array{buckets: list<array{key: string, label: string, count: int, percent: float}>, total_deals: int}
     * }
     */
    private function calculateModalPayload(PaymentDetail $paymentDetail, string $period): array
    {
        [$periodStartAt, $periodEndAt] = $this->resolvePeriodBounds($period);
        $currencyCode = $paymentDetail->currency->getCode();

        $ordersQuery = Order::query()
            ->where('payment_detail_id', $paymentDetail->id)
            ->where('status', OrderStatus::SUCCESS)
            ->when($periodStartAt !== null, fn (Builder $query) => $query->where('created_at', '>=', $periodStartAt))
            ->when($periodEndAt !== null, fn (Builder $query) => $query->where('created_at', '<=', $periodEndAt));

        $volumeUnits = (int) (clone $ordersQuery)->sum('orders.amount');
        $bucketCaseSql = $this->modalDealAmountBucketCaseSql($currencyCode);

        $rows = (clone $ordersQuery)
            ->selectRaw("{$bucketCaseSql} as amount_bucket, COUNT(*) as deals_count")
            ->groupBy('amount_bucket')
            ->get();

        $distribution = $this->formatModalDealAmountDistributionRows($rows, $currencyCode);
        $paymentGateway = $paymentDetail->paymentGateways->first();

        return [
            'period' => $period,
            'period_options' => self::PERIOD_OPTIONS,
            'payment_detail' => [
                'id' => $paymentDetail->id,
                'uuid' => $paymentDetail->uuid,
                'name' => (string) $paymentDetail->name,
                'detail' => (string) $paymentDetail->detail,
                'detail_type' => $paymentDetail->detail_type->value,
                'is_archived' => $paymentDetail->archived_at !== null,
                'currency_code' => $currencyCode,
                'currency_symbol' => $paymentDetail->currency->getSymbol(),
                'payment_gateway' => $paymentGateway === null ? null : [
                    'name' => (string) $paymentGateway->name,
                    'logo_path' => $paymentGateway->logoUrl(),
                ],
            ],
            'volume' => Money::fromUnits((string) $volumeUnits, $currencyCode)->toBeauty(),
            'deals_count' => $distribution['total_deals'],
            'distribution' => $distribution,
        ];
    }

    /**
     * @return array{CarbonInterface|null, CarbonInterface|null}
     */
    private function resolvePeriodBounds(string $period): array
    {
        $now = now();

        return match ($period) {
            '1d' => [$now->copy()->subDay(), $now],
            '7d' => [$now->copy()->subDays(7), $now],
            '14d' => [$now->copy()->subDays(14), $now],
            '30d' => [$now->copy()->subDays(30), $now],
            'current_month' => [$now->copy()->startOfMonth(), $now],
            'all' => [null, null],
            default => [$now->copy()->startOfMonth(), $now],
        };
    }

    private function modalDealAmountBucketCaseSql(string $currencyCode): string
    {
        $conditions = [];

        foreach ($this->modalDealAmountBucketsForCurrency($currencyCode) as $bucketDefinition) {
            if ($bucketDefinition['max'] === null) {
                continue;
            }

            $maxUnits = $this->displayAmountToUnits($bucketDefinition['max'], $currencyCode);
            $bucketKey = $bucketDefinition['key'];
            $conditions[] = "WHEN CAST(orders.amount AS UNSIGNED) < {$maxUnits} THEN '{$bucketKey}'";
        }

        $buckets = $this->modalDealAmountBucketsForCurrency($currencyCode);
        $lastBucketKey = $buckets[array_key_last($buckets)]['key'];

        return 'CASE '.implode(' ', $conditions)." ELSE '{$lastBucketKey}' END";
    }

    /**
     * @return array{buckets: list<array{key: string, label: string, count: int, percent: float}>, total_deals: int}
     */
    public function formatModalDealAmountDistributionRows(iterable $rows, string $currencyCode): array
    {
        $countsByKey = [];

        foreach ($rows as $row) {
            $bucketKey = (string) ($row->amount_bucket ?? '');
            $countsByKey[$bucketKey] = ($countsByKey[$bucketKey] ?? 0) + (int) ($row->deals_count ?? 0);
        }

        $buckets = [];
        $totalDeals = 0;

        foreach ($this->modalDealAmountBucketsForCurrency($currencyCode) as $bucketDefinition) {
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
     * @return list<array{key: string, label: string, max: int|null}>
     */
    private function modalDealAmountBucketsForCurrency(string $currencyCode): array
    {
        $currencyCode = strtolower($currencyCode);

        if (self::$modalDealAmountBucketsCache === null) {
            self::$modalDealAmountBucketsCache = [];

            foreach (Currency::getAll() as $currency) {
                self::$modalDealAmountBucketsCache[$currency->getCode()] = $this->buildModalDealAmountBucketsForCurrency($currency);
            }
        }

        return self::$modalDealAmountBucketsCache[$currencyCode]
            ?? $this->buildModalDealAmountBucketsForCurrency(Currency::make($currencyCode));
    }

    /**
     * @return list<array{key: string, label: string, max: int|null}>
     */
    private function buildModalDealAmountBucketsForCurrency(Currency $currency): array
    {
        $currencyCode = $currency->getCode();
        $maxBoundaries = self::MODAL_DEAL_AMOUNT_BUCKET_MAX_BY_CURRENCY[$currencyCode] ?? [1000, 5000, 10000, 50000];
        $previousMax = 0;
        $buckets = [];

        foreach ($maxBoundaries as $maxFiat) {
            $buckets[] = [
                'key' => "{$currencyCode}_{$previousMax}_{$maxFiat}",
                'label' => $this->formatModalDealBucketLabel($previousMax, $maxFiat, $currency),
                'max' => $maxFiat,
            ];
            $previousMax = $maxFiat;
        }

        $buckets[] = [
            'key' => "{$currencyCode}_{$previousMax}_plus",
            'label' => 'от '.$this->formatDisplayAmount($previousMax).' '.$currency->getSymbol(),
            'max' => null,
        ];

        return $buckets;
    }

    private function formatModalDealBucketLabel(int $min, int $max, Currency $currency): string
    {
        if ($min <= 0) {
            return '0-'.$this->formatDisplayAmount($max).' '.$currency->getSymbol();
        }

        return $this->formatDisplayAmount($min).'-'.$this->formatDisplayAmount($max).' '.$currency->getSymbol();
    }

    private function formatDisplayAmount(int $amount): string
    {
        return number_format($amount, 0, '', ' ');
    }

    private function displayAmountToUnits(int $amount, string $currencyCode): int
    {
        if ($amount <= 0) {
            return 0;
        }

        return Money::fromPrecision((string) $amount, $currencyCode)->toUnitsInt();
    }
}
