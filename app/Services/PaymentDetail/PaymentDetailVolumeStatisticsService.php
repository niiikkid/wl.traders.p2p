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
     *         volume_from: string|null,
     *         volume_to: string|null,
     *         scope_positive_volume_count: int,
     *         excluded_by_volume_count: int,
     *         volume_min_units: int,
     *         volume_max_units: int,
     *     },
     *     volume_presets: list<array{value: string, label: string}>
     * }
     */
    public function buildChart(
        ?int $userId,
        ?CarbonInterface $periodStartAt,
        ?CarbonInterface $periodEndAt,
        ?int $barsLimit = self::DEFAULT_BARS_LIMIT,
        bool $includeArchived = false,
        ?int $paymentGatewayId = null,
        ?string $volumeFrom = null,
        ?string $volumeTo = null,
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

        $positiveVolumeBaseQuery = $this->positiveVolumeQuery($baseQuery, $ordersSumConstraint);
        $scopePositiveVolumeCount = (clone $positiveVolumeBaseQuery)->count();

        /** @var list<int> $allVolumes */
        $allVolumes = (clone $positiveVolumeBaseQuery)
            ->pluck('volume_usdt_units')
            ->map(fn (mixed $volume): int => (int) $volume)
            ->all();

        $volumePresetData = $this->buildVolumePresetData($allVolumes);
        $allowedVolumeUnits = array_map(
            fn (array $option): int => (int) $option['value'],
            $volumePresetData['options'],
        );
        [$volumeFromUnits, $volumeToUnits] = $this->resolveVolumeBounds(
            $volumeFrom,
            $volumeTo,
            $allowedVolumeUnits,
        );

        $filteredVolumeQuery = $this->applyVolumeBounds(
            clone $positiveVolumeBaseQuery,
            $volumeFromUnits,
            $volumeToUnits,
        );

        $positiveVolumeCount = (clone $filteredVolumeQuery)->count();
        $excludedByVolumeCount = max(0, $scopePositiveVolumeCount - $positiveVolumeCount);

        $topRowsQuery = $this->applyVolumeBounds(
            (clone $baseQuery)
                ->select(['id', 'name', 'archived_at'])
                ->withSum(['orders as volume_usdt_units' => $ordersSumConstraint], 'total_profit')
                ->having('volume_usdt_units', '>', 0),
            $volumeFromUnits,
            $volumeToUnits,
        )
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
                'hidden_zero_volume_count' => max(0, $activePaymentDetailsCount - $scopePositiveVolumeCount),
                'excluded_over_limit_count' => $barsLimit === null
                    ? 0
                    : max(0, $positiveVolumeCount - $barsLimit),
                'excluded_by_volume_count' => $excludedByVolumeCount,
                'scope_positive_volume_count' => $scopePositiveVolumeCount,
                'positive_volume_count' => $positiveVolumeCount,
                'bars_limit' => $barsLimitLabel,
                'bars_limit_is_all' => $barsLimit === null,
                'include_archived' => $includeArchived,
                'payment_gateway_id' => $paymentGatewayId,
                'volume_from' => $volumeFromUnits === null ? null : (string) $volumeFromUnits,
                'volume_to' => $volumeToUnits === null ? null : (string) $volumeToUnits,
                'volume_min_units' => $volumePresetData['min_units'],
                'volume_max_units' => $volumePresetData['max_units'],
                'archived_in_scope_count' => $archivedInScopeCount,
                'archived_on_chart_count' => $archivedOnChartCount,
            ],
            'volume_presets' => $volumePresetData['options'],
        ];
    }

    /**
     * @param  list<int>  $volumes
     * @return array{min_units: int, max_units: int, options: list<array{value: string, label: string}>}
     */
    public function buildVolumePresetData(array $volumes): array
    {
        if ($volumes === []) {
            return [
                'min_units' => 0,
                'max_units' => 0,
                'options' => [],
            ];
        }

        sort($volumes);

        $minUnits = $volumes[0];
        $maxUnits = $volumes[array_key_last($volumes)];
        $minUsdt = $this->unitsToDisplayUsdt($minUnits);
        $maxUsdt = $this->unitsToDisplayUsdt($maxUnits);
        $niceUsdtSteps = $this->buildNiceUsdtSteps($minUsdt, $maxUsdt);

        $options = [];
        $seenValues = [];

        foreach ($niceUsdtSteps as $usdt) {
            $units = $this->displayUsdtToUnits($usdt);
            $value = (string) $units;

            if ($units < 0 || isset($seenValues[$value])) {
                continue;
            }

            $seenValues[$value] = true;
            $options[] = [
                'value' => $value,
                'label' => $this->formatVolumePresetLabel($units),
            ];
        }

        return [
            'min_units' => $minUnits,
            'max_units' => $maxUnits,
            'options' => $options,
        ];
    }

    /**
     * @param  list<int>  $allowedUnits
     * @return array{0: int|null, 1: int|null}
     */
    public function resolveVolumeBounds(
        ?string $volumeFrom,
        ?string $volumeTo,
        array $allowedUnits,
    ): array {
        $volumeFromUnits = $this->resolveVolumeBound($volumeFrom, $allowedUnits);
        $volumeToUnits = $this->resolveVolumeBound($volumeTo, $allowedUnits);

        if ($volumeFromUnits !== null && $volumeToUnits !== null && $volumeFromUnits > $volumeToUnits) {
            return [$volumeToUnits, $volumeFromUnits];
        }

        return [$volumeFromUnits, $volumeToUnits];
    }

    /**
     * @param  list<int>  $allowedUnits
     */
    public function resolveVolumeBound(?string $value, array $allowedUnits): ?int
    {
        $normalizedValue = trim((string) ($value ?? ''));

        if ($normalizedValue === '' || ! ctype_digit($normalizedValue)) {
            return null;
        }

        $units = (int) $normalizedValue;

        return in_array($units, $allowedUnits, true) ? $units : null;
    }

    private function formatVolumePresetLabel(int $units): string
    {
        if ($units === 0) {
            return '0 USDT';
        }

        return $this->formatCompactUsdt($this->unitsToDisplayUsdt($units)).' USDT';
    }

    /**
     * @return list<float>
     */
    private function buildNiceUsdtSteps(float $minUsdt, float $maxUsdt): array
    {
        $maxUsdt = max($maxUsdt, 0.01);
        $minUsdt = max($minUsdt, 0.01);

        $niceStep = $this->resolveNiceStepForRange($minUsdt, $maxUsdt);
        $start = $this->snapDown($minUsdt, $niceStep);
        $end = $this->snapUp($maxUsdt, $niceStep);

        if ($end < $start) {
            $end = $start;
        }

        $steps = [];
        $maxSteps = 8;

        for ($current = $start; $current <= $end + ($niceStep / 1000) && count($steps) < $maxSteps; $current += $niceStep) {
            $steps[] = $this->roundUsdtForStep($current, $niceStep);
        }

        if ($steps === []) {
            $steps[] = $this->roundUsdtForStep($minUsdt, $niceStep);
        }

        if (end($steps) < $maxUsdt) {
            $steps[] = $this->roundUsdtForStep($end, $niceStep);
        }

        $steps = array_values(array_unique($steps));

        return $this->insertMidpointsBetweenSteps([0.0, ...$steps]);
    }

    /**
     * @param  list<float>  $steps
     * @return list<float>
     */
    private function insertMidpointsBetweenSteps(array $steps): array
    {
        sort($steps);
        $steps = array_values(array_unique($steps));

        if ($steps === []) {
            return [0.0];
        }

        $result = [];

        for ($index = 0; $index < count($steps); $index++) {
            $result[] = $steps[$index];

            if ($index >= count($steps) - 1) {
                continue;
            }

            $lower = $steps[$index];
            $upper = $steps[$index + 1];
            $midpoint = $this->roundMidpoint($lower, $upper, ($lower + $upper) / 2);

            if ($midpoint > $lower && $midpoint < $upper) {
                $result[] = $midpoint;
            }
        }

        sort($result);

        return array_values(array_unique($result));
    }

    private function roundMidpoint(float $lower, float $upper, float $midpoint): float
    {
        $span = $upper - $lower;

        if ($span >= 1) {
            return (float) (int) round($midpoint);
        }

        if ($span >= 0.1) {
            return round($midpoint, 1);
        }

        return round($midpoint, 2);
    }

    private function resolveNiceStepForRange(float $minUsdt, float $maxUsdt): float
    {
        $range = max($maxUsdt - $minUsdt, $maxUsdt / 6);

        return $this->niceStep($range / 5);
    }

    private function niceStep(float $rawStep): float
    {
        if ($rawStep <= 0) {
            return 1.0;
        }

        $exponent = 10 ** floor(log10($rawStep));
        $fraction = $rawStep / $exponent;

        if ($fraction <= 1) {
            return $exponent;
        }

        if ($fraction <= 2) {
            return 2 * $exponent;
        }

        if ($fraction <= 5) {
            return 5 * $exponent;
        }

        return 10 * $exponent;
    }

    private function snapDown(float $value, float $step): float
    {
        if ($step <= 0) {
            return $value;
        }

        $snapped = floor($value / $step) * $step;

        return $snapped > 0 ? $snapped : $step;
    }

    private function snapUp(float $value, float $step): float
    {
        if ($step <= 0) {
            return $value;
        }

        $snapped = ceil($value / $step) * $step;

        return $snapped > 0 ? $snapped : $step;
    }

    private function roundUsdtForStep(float $value, float $step): float
    {
        if ($step >= 1) {
            return (float) (int) round($value);
        }

        if ($step >= 0.1) {
            return round($value, 1);
        }

        return round($value, 2);
    }

    private function unitsToDisplayUsdt(int $units): float
    {
        return (float) Money::fromUnits((string) $units, Currency::USDT()->getCode())->toPrecision();
    }

    private function displayUsdtToUnits(float $usdt): int
    {
        if ($usdt <= 0) {
            return 0;
        }

        $amount = fmod($usdt, 1.0) === 0.0
            ? (string) (int) round($usdt)
            : rtrim(rtrim(number_format($usdt, 2, '.', ''), '0'), '.');

        return Money::fromPrecision($amount, Currency::USDT()->getCode())->toUnitsInt();
    }

    private function formatCompactUsdt(float $value): string
    {
        if ($value <= 0) {
            return '0';
        }

        if ($value >= 1_000_000) {
            $scaled = $value / 1_000_000;

            return $this->formatCompactScaled($scaled).'M';
        }

        if ($value >= 1000) {
            $scaled = $value / 1000;

            return $this->formatCompactScaled($scaled).'k';
        }

        if ($value >= 100) {
            return (string) (int) round($value);
        }

        if ($value >= 10) {
            return (string) (int) round($value);
        }

        if ($value >= 1) {
            return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function formatCompactScaled(float $scaled): string
    {
        $rounded = round($scaled, 1);

        if (abs($rounded - (int) $rounded) < 0.05) {
            return (string) (int) round($rounded);
        }

        return rtrim(rtrim(number_format($rounded, 1, '.', ''), '0'), '.');
    }

    private function positiveVolumeQuery(Builder $baseQuery, callable $ordersSumConstraint): Builder
    {
        return (clone $baseQuery)
            ->select(['payment_details.id'])
            ->withSum(['orders as volume_usdt_units' => $ordersSumConstraint], 'total_profit')
            ->having('volume_usdt_units', '>', 0);
    }

    private function applyVolumeBounds(
        Builder $query,
        ?int $volumeFromUnits,
        ?int $volumeToUnits,
    ): Builder {
        return $query
            ->when(
                $volumeFromUnits !== null,
                fn (Builder $builder) => $builder->having('volume_usdt_units', '>=', $volumeFromUnits),
            )
            ->when(
                $volumeToUnits !== null,
                fn (Builder $builder) => $builder->having('volume_usdt_units', '<=', $volumeToUnits),
            );
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
