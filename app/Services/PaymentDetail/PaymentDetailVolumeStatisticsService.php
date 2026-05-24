<?php

declare(strict_types=1);

namespace App\Services\PaymentDetail;

use App\Enums\OrderStatus;
use App\Models\PaymentDetail;
use App\Models\PaymentGateway;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class PaymentDetailVolumeStatisticsService
{
    public const int DEFAULT_BARS_LIMIT = 100;

    public const int MAX_BARS_LIMIT = 200;

    /** @var list<int> */
    public const array ALLOWED_BARS_LIMITS = [25, 50, 75, 100, 200];

    /**
     * @return array{
     *     items: list<array{id: int, name: string, label: string, volume_usdt_units: int, volume_usdt: string, volume_usdt_chart: float}>,
     *     meta: array{
     *         active_payment_details_count: int,
     *         displayed_count: int,
     *         hidden_zero_volume_count: int,
     *         excluded_over_limit_count: int,
     *         positive_volume_count: int,
     *         bars_limit: string,
     *         bars_limit_is_all: bool,
     *         include_archived: bool,
     *         payment_gateway_id: int|null,
     *     }
     * }
     */
    public function buildChart(
        ?int $userId,
        ?CarbonInterface $periodStartAt,
        ?CarbonInterface $periodEndAt,
        ?int $barsLimit = self::DEFAULT_BARS_LIMIT,
        bool $includeArchived = false,
        ?int $paymentGatewayId = null,
    ): array {
        $baseQuery = $this->basePaymentDetailQuery($userId, $includeArchived, $paymentGatewayId);

        $ordersSumConstraint = function (Builder $query) use ($periodStartAt, $periodEndAt): void {
            $query->where('status', OrderStatus::SUCCESS);

            if ($periodStartAt !== null) {
                $query->where('created_at', '>=', $periodStartAt);
            }

            if ($periodEndAt !== null) {
                $query->where('created_at', '<=', $periodEndAt);
            }
        };

        $activePaymentDetailsCount = (clone $baseQuery)->count();

        $positiveVolumeCount = (clone $baseQuery)
            ->withSum(['orders as volume_usdt_units' => $ordersSumConstraint], 'total_profit')
            ->having('volume_usdt_units', '>', 0)
            ->count();

        $topRowsQuery = (clone $baseQuery)
            ->select(['id', 'name', 'archived_at'])
            ->withSum(['orders as volume_usdt_units' => $ordersSumConstraint], 'total_profit')
            ->having('volume_usdt_units', '>', 0)
            ->orderByDesc('volume_usdt_units')
            ->orderBy('name');

        if ($barsLimit !== null) {
            $topRowsQuery->limit($barsLimit);
        }

        $topRows = $topRowsQuery->get();

        $items = $topRows
            ->map(function (PaymentDetail $paymentDetail): array {
                $volumeUnits = (int) ($paymentDetail->volume_usdt_units ?? 0);
                $volumeMoney = Money::fromUnits((string) $volumeUnits, Currency::USDT()->getCode());

                $label = trim((string) $paymentDetail->name).' #'.$paymentDetail->id;

                if ($paymentDetail->archived_at !== null) {
                    $label .= ' (архив)';
                }

                return [
                    'id' => $paymentDetail->id,
                    'name' => (string) $paymentDetail->name,
                    'label' => $label,
                    'volume_usdt_units' => $volumeUnits,
                    'volume_usdt' => $volumeMoney->toBeauty(),
                    'volume_usdt_chart' => (float) $volumeMoney->toPrecision(),
                ];
            })
            ->all();

        $barsLimitLabel = $barsLimit === null ? 'all' : (string) $barsLimit;
        $archivedInScopeCount = $includeArchived
            ? (clone $baseQuery)->whereNotNull('archived_at')->count()
            : 0;
        $archivedOnChartCount = $topRows->filter(
            fn (PaymentDetail $paymentDetail): bool => $paymentDetail->archived_at !== null,
        )->count();

        return [
            'items' => $this->arrangePyramid($items),
            'meta' => [
                'active_payment_details_count' => $activePaymentDetailsCount,
                'displayed_count' => count($items),
                'hidden_zero_volume_count' => max(0, $activePaymentDetailsCount - $positiveVolumeCount),
                'excluded_over_limit_count' => $barsLimit === null
                    ? 0
                    : max(0, $positiveVolumeCount - $barsLimit),
                'positive_volume_count' => $positiveVolumeCount,
                'bars_limit' => $barsLimitLabel,
                'bars_limit_is_all' => $barsLimit === null,
                'include_archived' => $includeArchived,
                'payment_gateway_id' => $paymentGatewayId,
                'archived_in_scope_count' => $archivedInScopeCount,
                'archived_on_chart_count' => $archivedOnChartCount,
            ],
        ];
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public function bankOptions(?int $userId, bool $includeArchived): array
    {
        return PaymentGateway::query()
            ->select(['id', 'name', 'code'])
            ->whereHas('paymentDetails', function (Builder $query) use ($userId, $includeArchived): void {
                $query->when(! $includeArchived, fn (Builder $builder) => $builder->whereNull('archived_at'))
                    ->when($userId !== null, fn (Builder $builder) => $builder->where('user_id', $userId));
            })
            ->orderBy('name')
            ->orderBy('code')
            ->get()
            ->map(fn (PaymentGateway $paymentGateway): array => [
                'value' => $paymentGateway->id,
                'label' => trim($paymentGateway->name.' ('.$paymentGateway->code.')'),
            ])
            ->values()
            ->all();
    }

    public function resolveBarsLimit(?string $barsLimit): int
    {
        $value = trim((string) ($barsLimit ?? ''));

        if ($value === '' || $value === (string) self::DEFAULT_BARS_LIMIT) {
            return self::DEFAULT_BARS_LIMIT;
        }

        if (! ctype_digit($value)) {
            return self::DEFAULT_BARS_LIMIT;
        }

        $limit = (int) $value;

        if (! in_array($limit, self::ALLOWED_BARS_LIMITS, true)) {
            return self::DEFAULT_BARS_LIMIT;
        }

        return $limit;
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

        $periodStartAt = match ($period) {
            '1d' => now()->subDay(),
            '7d' => now()->subDays(7),
            '14d' => now()->subDays(14),
            '30d' => now()->subDays(30),
            default => null,
        };

        return [$periodStartAt, null];
    }

    /**
     * @param  list<array{id: int, name: string, label: string, volume_usdt_units: int, volume_usdt: string, volume_usdt_chart: float}>  $items
     * @return list<array{id: int, name: string, label: string, volume_usdt_units: int, volume_usdt: string, volume_usdt_chart: float}>
     */
    public function arrangePyramid(array $items): array
    {
        if ($items === []) {
            return [];
        }

        usort($items, function (array $left, array $right): int {
            $volumeComparison = $right['volume_usdt_units'] <=> $left['volume_usdt_units'];

            if ($volumeComparison !== 0) {
                return $volumeComparison;
            }

            return $left['id'] <=> $right['id'];
        });

        $count = count($items);
        /** @var list<array{id: int, name: string, label: string, volume_usdt_units: int, volume_usdt: string, volume_usdt_chart: float}|null> $result */
        $result = array_fill(0, $count, null);
        $center = intdiv($count - 1, 2);
        $result[$center] = $items[0];

        $left = $center - 1;
        $right = $center + 1;

        for ($index = 1; $index < $count; $index++) {
            if ($index % 2 === 1) {
                if ($left >= 0) {
                    $result[$left--] = $items[$index];

                    continue;
                }

                if ($right < $count) {
                    $result[$right++] = $items[$index];
                }

                continue;
            }

            if ($right < $count) {
                $result[$right++] = $items[$index];

                continue;
            }

            if ($left >= 0) {
                $result[$left--] = $items[$index];
            }
        }

        return array_values(array_filter($result, fn (?array $item): bool => $item !== null));
    }

    /**
     * @return array{labels: list<string>, data: list<float>, colors: list<string>, volumes: list<string>}
     */
    public function formatChartPayload(array $items): array
    {
        if ($items === []) {
            return [
                'labels' => [],
                'data' => [],
                'colors' => [],
                'volumes' => [],
            ];
        }

        $units = array_column($items, 'volume_usdt_units');
        $minUnits = min($units);
        $maxUnits = max($units);

        return [
            'labels' => array_column($items, 'label'),
            'data' => array_column($items, 'volume_usdt_chart'),
            'colors' => collect($items)
                ->map(fn (array $item): string => $this->volumeColor((int) $item['volume_usdt_units'], $minUnits, $maxUnits))
                ->all(),
            'volumes' => array_column($items, 'volume_usdt'),
        ];
    }

    private function basePaymentDetailQuery(
        ?int $userId,
        bool $includeArchived,
        ?int $paymentGatewayId,
    ): Builder {
        return PaymentDetail::query()
            ->when(! $includeArchived, fn (Builder $query) => $query->whereNull('archived_at'))
            ->when($userId !== null, fn (Builder $query) => $query->where('user_id', $userId))
            ->when(
                $paymentGatewayId !== null,
                fn (Builder $query) => $query->whereHas(
                    'paymentGateways',
                    fn (Builder $subQuery) => $subQuery->where('payment_gateways.id', $paymentGatewayId),
                ),
            );
    }

    private function volumeColor(int $volumeUnits, int $minUnits, int $maxUnits): string
    {
        if ($maxUnits <= $minUnits) {
            return 'hsl(120, 65%, 45%)';
        }

        $ratio = ($volumeUnits - $minUnits) / ($maxUnits - $minUnits);
        $hue = (int) round(120 * $ratio);

        return "hsl({$hue}, 70%, 45%)";
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
