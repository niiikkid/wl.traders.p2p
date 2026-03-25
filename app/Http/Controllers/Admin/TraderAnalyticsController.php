<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentDetail;
use App\Models\PaymentDetailEnabledPeriod;
use App\Models\User;
use App\Models\UserOnlinePeriod;
use App\Services\Money\Currency;
use App\Services\Money\Money;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TraderAnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $currency_codes = Currency::getAllCodes();
        $default_currency = in_array('uah', $currency_codes, true)
            ? 'uah'
            : ($currency_codes[0] ?? 'rub');

        $validated = $request->validate([
            'period' => ['nullable', 'string', 'in:today,7d,14d,30d'],
            'currency' => ['nullable', 'string', 'in:' . implode(',', $currency_codes)],
            'amount_ranges' => ['nullable', 'string', 'max:500'],
            'tab' => ['nullable', 'string', 'in:overview,trader'],
            'trader_id' => ['nullable', 'integer'],
        ]);

        $period_options = [
            'today' => ['label' => 'Сегодня', 'today' => true],
            '7d' => ['label' => '7 дней', 'days' => 7],
            '14d' => ['label' => '14 дней', 'days' => 14],
            '30d' => ['label' => '30 дней', 'days' => 30],
        ];

        $period = $validated['period'] ?? 'today';
        $selected_currency = $validated['currency'] ?? $default_currency;
        $amount_ranges_raw = $validated['amount_ranges'] ?? null;
        $selected_tab = $validated['tab'] ?? 'overview';
        $selected_trader_id = isset($validated['trader_id']) ? (int) $validated['trader_id'] : null;
        $operation_threshold = services()->settings()->getTraderAnalyticsOperationThresholdForCurrency(
            Currency::make($selected_currency)
        );
        $amount_ranges = $this->parseAmountRanges(
            $amount_ranges_raw,
            $selected_currency,
            (string) $operation_threshold
        );
        $days = $period_options[$period]['days'] ?? null;
        $is_today = (bool) ($period_options[$period]['today'] ?? false);

        $end_at = now();
        $start_at = $is_today
            ? now()->startOfDay()
            : now()->subDays($days - 1)->startOfDay();

        $total_traders = User::role('Trader')->count();
        $trader_ids = User::role('Trader')->pluck('id');

        $enabled_details_by_day = [];
        $enabled_percent_sum = 0;

        $day_count_for_chart = max(
            $days ?? 0,
            $start_at->copy()->startOfDay()->diffInDays($end_at->copy()->startOfDay()) + 1
        );

        $day_loop_start = $start_at->copy()->startOfDay();

        for ($index = 0; $index < $day_count_for_chart; $index++) {
            $day = $day_loop_start->copy()->addDays($index);
            $day_end = $day->copy()->endOfDay();

            $total_details = PaymentDetail::query()
                ->where('currency', $selected_currency)
                ->where('created_at', '<=', $day_end)
                ->where(function ($query) use ($day_end) {
                    $query
                        ->whereNull('archived_at')
                        ->orWhere('archived_at', '>', $day_end);
                })
                ->count();

            $enabled_details = PaymentDetail::query()
                ->where('currency', $selected_currency)
                ->whereIn(
                    'id',
                    PaymentDetailEnabledPeriod::query()
                        ->where('started_at', '<=', $day_end)
                        ->where(function ($query) use ($day) {
                            $query
                                ->whereNull('ended_at')
                                ->orWhere('ended_at', '>=', $day->copy()->startOfDay());
                        })
                        ->select('payment_detail_id')
                )
                ->count();

            $enabled_percent = $total_details > 0
                ? round(($enabled_details / $total_details) * 100, 2)
                : 0;

            $enabled_percent_sum += $enabled_percent;

            $enabled_details_by_day[] = [
                'date' => $day->format('Y-m-d'),
                'date_label' => $day->format('d.m'),
                'enabled_count' => $enabled_details,
                'total_count' => $total_details,
                'enabled_percent' => $enabled_percent,
            ];
        }

        $activity_rows = UserOnlinePeriod::query()
            ->where('ended_at', '>=', $start_at)
            ->where('started_at', '<=', $end_at)
            ->whereIn('user_id', $trader_ids)
            ->groupBy('user_id')
            ->selectRaw(
                'user_id as trader_id, SUM(GREATEST(TIMESTAMPDIFF(SECOND, GREATEST(started_at, ?), LEAST(ended_at, ?)), 0)) as active_seconds',
                [$start_at, $end_at]
            )
            ->get();

        $activity_by_trader = $activity_rows
            ->mapWithKeys(fn ($row) => [(int) $row->trader_id => (int) $row->active_seconds])
            ->filter(fn (int $seconds) => $seconds > 0);

        $active_trader_ids = $activity_by_trader->keys()->values();
        $active_traders_count = $active_trader_ids->count();
        $total_active_seconds = (int) $activity_by_trader->sum();

        $orders_query = Order::query()
            ->whereBetween('created_at', [$start_at, $end_at])
            ->where('currency', $selected_currency)
            ->whereIn('trader_id', $trader_ids);

        $operations_count = (clone $orders_query)->count();
        $processed_operations_count = (clone $orders_query)->whereNotNull('finished_at')->count();
        $avg_processing_seconds = (float) ((clone $orders_query)
            ->whereNotNull('finished_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, finished_at)) as avg_seconds')
            ->value('avg_seconds') ?? 0);

        $selected_threshold_units = (int) Money::fromPrecision($operation_threshold, $selected_currency)->toUnits();

        $operations_over_300_count = (clone $orders_query)
            ->whereRaw('CAST(amount AS SIGNED) >= ?', [$selected_threshold_units])
            ->count();

        $trader_range_query = (clone $orders_query)
            ->selectRaw('trader_id, COUNT(*) as total_operations');

        foreach ($amount_ranges as $index => $range) {
            $column = 'range_' . $index;

            if ($range['max_units'] === null) {
                $trader_range_query->selectRaw(
                    "SUM(CASE WHEN CAST(amount AS SIGNED) >= ? THEN 1 ELSE 0 END) as {$column}",
                    [$range['min_units']]
                );
                continue;
            }

            $trader_range_query->selectRaw(
                "SUM(CASE WHEN CAST(amount AS SIGNED) >= ? AND CAST(amount AS SIGNED) <= ? THEN 1 ELSE 0 END) as {$column}",
                [$range['min_units'], $range['max_units']]
            );
        }

        $trader_range_rows = $trader_range_query
            ->groupBy('trader_id')
            ->orderByDesc('total_operations')
            ->limit(100)
            ->get();

        $trader_range_trader_ids = $trader_range_rows->pluck('trader_id')->filter()->values();
        $trader_range_traders = User::query()
            ->whereIn('id', $trader_range_trader_ids)
            ->get(['id', 'name', 'email', 'is_online'])
            ->keyBy('id');

        $trader_amount_range_stats = $trader_range_rows
            ->map(function ($row) use ($amount_ranges, $trader_range_traders) {
                $trader = $trader_range_traders->get((int) $row->trader_id);
                $ranges = [];

                foreach ($amount_ranges as $index => $range) {
                    $ranges[] = [
                        'key' => $range['key'],
                        'label' => $range['label'],
                        'count' => (int) ($row->{'range_' . $index} ?? 0),
                    ];
                }

                return [
                    'trader_id' => (int) $row->trader_id,
                    'name' => $trader?->name ?? 'Удален',
                    'email' => $trader?->email ?? '-',
                    'is_online' => (bool) ($trader?->is_online ?? false),
                    'total_operations' => (int) ($row->total_operations ?? 0),
                    'ranges' => $ranges,
                ];
            })
            ->values()
            ->all();

        $top_start_at = now()->subDays(6)->startOfDay();
        $top_end_at = now()->endOfDay();

        $top_rows = Order::query()
            ->whereBetween('created_at', [$top_start_at, $top_end_at])
            ->where('currency', $selected_currency)
            ->whereIn('trader_id', $trader_ids)
            ->where('status', OrderStatus::SUCCESS)
            ->selectRaw('trader_id, COUNT(*) as operations_count, AVG(TIMESTAMPDIFF(SECOND, created_at, finished_at)) as avg_processing_seconds')
            ->groupBy('trader_id')
            ->orderByDesc('operations_count')
            ->limit(10)
            ->get();

        $top_trader_ids = $top_rows->pluck('trader_id')->filter()->values();
        $top_traders_map = User::query()
            ->whereIn('id', $top_trader_ids)
            ->get(['id', 'name', 'email', 'is_online'])
            ->keyBy('id');

        $top_traders = $top_rows
            ->values()
            ->map(function ($row, $index) use ($top_traders_map) {
                $trader = $top_traders_map->get((int) $row->trader_id);
                $avg_seconds = (float) ($row->avg_processing_seconds ?? 0);

                return [
                    'rank' => $index + 1,
                    'trader_id' => (int) $row->trader_id,
                    'name' => $trader?->name ?? 'Удален',
                    'email' => $trader?->email ?? '-',
                    'is_online' => (bool) ($trader?->is_online ?? false),
                    'operations_count' => (int) $row->operations_count,
                    'avg_processing_seconds' => (int) round($avg_seconds),
                    'avg_processing_human' => $this->formatSecondsToHuman((int) round($avg_seconds)),
                ];
            })
            ->all();

        $active_traders = $activity_by_trader
            ->sortDesc()
            ->take(10)
            ->map(function ($seconds, $trader_id) {
                return [
                    'trader_id' => (int) $trader_id,
                    'active_seconds' => (int) $seconds,
                ];
            })
            ->values();

        $active_trader_users = User::query()
            ->whereIn('id', $active_traders->pluck('trader_id'))
            ->get(['id', 'name', 'email', 'is_online'])
            ->keyBy('id');

        $active_traders = $active_traders
            ->map(function ($row) use ($active_trader_users) {
                $user = $active_trader_users->get($row['trader_id']);

                return [
                    'trader_id' => $row['trader_id'],
                    'name' => $user?->name ?? 'Удален',
                    'email' => $user?->email ?? '-',
                    'is_online' => (bool) ($user?->is_online ?? false),
                    'active_seconds' => $row['active_seconds'],
                    'active_human' => $this->formatSecondsToHuman($row['active_seconds']),
                ];
            })
            ->all();

        $selected_trader = null;
        if ($selected_trader_id !== null) {
            $selected_trader = User::query()
                ->role('Trader')
                ->whereKey($selected_trader_id)
                ->first(['id', 'name', 'email', 'is_online']);
        }

        $individual_by_day = [];
        $individual_summary = [
            'operations_count' => 0,
            'processed_operations_count' => 0,
            'avg_processing_seconds' => 0,
            'avg_processing_human' => '0 мин',
        ];

        if ($selected_trader !== null) {
            $avg_processing_seconds_sum = 0;
            $avg_processing_days_count = 0;

            for ($index = 0; $index < $day_count_for_chart; $index++) {
                $day = $day_loop_start->copy()->addDays($index);
                $day_start = $day->copy()->startOfDay();
                $day_end = $day->copy()->endOfDay()->min($end_at);

                $day_orders_query = Order::query()
                    ->whereBetween('created_at', [$day_start, $day_end])
                    ->where('currency', $selected_currency)
                    ->where('trader_id', $selected_trader->id);

                $day_operations_count = (clone $day_orders_query)->count();
                $day_processed_operations_count = (clone $day_orders_query)->whereNotNull('finished_at')->count();
                $day_avg_processing_seconds = (float) ((clone $day_orders_query)
                    ->whereNotNull('finished_at')
                    ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, finished_at)) as avg_seconds')
                    ->value('avg_seconds') ?? 0);

                $range_counts = [];
                foreach ($amount_ranges as $range) {
                    $range_query = clone $day_orders_query;
                    if ($range['max_units'] === null) {
                        $range_query->whereRaw('CAST(amount AS SIGNED) >= ?', [$range['min_units']]);
                    } else {
                        $range_query->whereRaw(
                            'CAST(amount AS SIGNED) >= ? AND CAST(amount AS SIGNED) <= ?',
                            [$range['min_units'], $range['max_units']]
                        );
                    }

                    $range_counts[] = [
                        'key' => $range['key'],
                        'label' => $range['label'],
                        'count' => $range_query->count(),
                    ];
                }

                $individual_summary['operations_count'] += $day_operations_count;
                $individual_summary['processed_operations_count'] += $day_processed_operations_count;

                if ($day_avg_processing_seconds > 0) {
                    $avg_processing_seconds_sum += (int) round($day_avg_processing_seconds);
                    $avg_processing_days_count++;
                }

                $individual_by_day[] = [
                    'date' => $day->format('Y-m-d'),
                    'date_label' => $day->format('d.m'),
                    'operations_count' => $day_operations_count,
                    'processed_operations_count' => $day_processed_operations_count,
                    'avg_processing_seconds' => (int) round($day_avg_processing_seconds),
                    'avg_processing_human' => $this->formatSecondsToHuman((int) round($day_avg_processing_seconds)),
                    'ranges' => $range_counts,
                ];
            }

            $individual_summary['avg_processing_seconds'] = $avg_processing_days_count > 0
                ? (int) round($avg_processing_seconds_sum / $avg_processing_days_count)
                : 0;
            $individual_summary['avg_processing_human'] = $this->formatSecondsToHuman($individual_summary['avg_processing_seconds']);
        }

        return Inertia::render($this->getPageComponent(), [
            'filters' => [
                'period' => $period,
                'currency' => $selected_currency,
                'amount_ranges' => $amount_ranges_raw,
                'tab' => $selected_tab,
                'trader_id' => $selected_trader?->id,
            ],
            'routes' => [
                'index' => $this->getIndexRouteName(),
                'update_threshold' => $this->getUpdateThresholdRouteName(),
                'search_traders' => $this->getSearchTradersRouteName(),
            ],
            'amountRanges' => collect($amount_ranges)
                ->map(fn (array $range) => [
                    'key' => $range['key'],
                    'label' => $range['label'],
                    'min' => $range['min'],
                    'max' => $range['max'],
                ])
                ->values()
                ->all(),
            'currencyOptions' => Currency::getAll()
                ->map(function (Currency $currency) {
                    return [
                        'value' => $currency->getCode(),
                        'label' => strtoupper($currency->getCode()) . ' (' . $currency->getSymbol() . ')',
                    ];
                })
                ->values()
                ->all(),
            'periodOptions' => collect($period_options)
                ->map(function (array $item, string $key) {
                    return [
                        'value' => $key,
                        'label' => $item['label'],
                    ];
                })
                ->values()
                ->all(),
            'summary' => [
                'date_from' => $start_at->format('Y-m-d'),
                'date_to' => $end_at->format('Y-m-d'),
                'currency' => $selected_currency,
                'total_traders' => $total_traders,
                'active_traders_count' => $active_traders_count,
                'active_traders_percent' => $total_traders > 0
                    ? round(($active_traders_count / $total_traders) * 100, 2)
                    : 0,
                'total_active_seconds' => $total_active_seconds,
                'total_active_human' => $this->formatSecondsToHuman($total_active_seconds),
                'avg_active_hours_per_trader' => $total_traders > 0
                    ? round($total_active_seconds / 3600 / $total_traders, 2)
                    : 0,
                'operations_count' => $operations_count,
                'operations_threshold' => $operation_threshold,
                'operations_over_300_count' => $operations_over_300_count,
                'processed_operations_count' => $processed_operations_count,
                'avg_processing_seconds' => (int) round($avg_processing_seconds),
                'avg_processing_human' => $this->formatSecondsToHuman((int) round($avg_processing_seconds)),
                'avg_enabled_percent' => $day_count_for_chart > 0
                    ? round($enabled_percent_sum / $day_count_for_chart, 2)
                    : 0,
            ],
            'enabledDetailsByDay' => array_reverse($enabled_details_by_day),
            'topTraders' => $top_traders,
            'activeTraders' => $active_traders,
            'traderAmountRangeStats' => $trader_amount_range_stats,
            'individualTrader' => $selected_trader === null ? null : [
                'id' => $selected_trader->id,
                'name' => $selected_trader->name,
                'email' => $selected_trader->email,
                'is_online' => (bool) $selected_trader->is_online,
            ],
            'individualByDay' => array_reverse($individual_by_day),
            'individualSummary' => $individual_summary,
        ]);
    }

    public function searchTraders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($validated['query'] ?? ''));

        $query = User::query()
            ->role('Trader')
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->limit(20);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');

                if (is_numeric($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $items = $query
            ->get()
            ->map(fn (User $trader) => [
                'value' => (string) $trader->id,
                'label' => trim($trader->name . ' (' . $trader->email . ')'),
            ])
            ->values()
            ->all();

        return response()->json($items);
    }

    public function updateOperationsThreshold(Request $request)
    {
        $validated = $request->validate([
            'currency' => ['required', 'string', 'in:' . implode(',', Currency::getAllCodes())],
            'threshold' => ['required', 'numeric', 'gt:0'],
        ]);

        services()->settings()->updateTraderAnalyticsOperationThreshold(
            $validated['currency'],
            (string) $validated['threshold']
        );

        return back();
    }

    private function formatSecondsToHuman(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0 мин';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return "{$hours} ч {$minutes} мин";
        }

        return "{$minutes} мин";
    }

    private function parseAmountRanges(?string $rangesRaw, string $currency, string $operationThreshold): array
    {
        $ranges = [];
        $segments = array_filter(array_map('trim', explode(',', (string) $rangesRaw)));

        foreach ($segments as $segment) {
            if (! str_contains($segment, '-')) {
                continue;
            }

            [$minRaw, $maxRaw] = array_pad(explode('-', $segment, 2), 2, '');

            $minRaw = trim($minRaw);
            $maxRaw = trim($maxRaw);

            if ($minRaw === '' || ! is_numeric($minRaw) || (float) $minRaw < 0) {
                continue;
            }

            if ($maxRaw !== '' && (! is_numeric($maxRaw) || (float) $maxRaw < (float) $minRaw)) {
                continue;
            }

            $min = (string) $minRaw;
            $max = $maxRaw === '' ? null : (string) $maxRaw;

            $ranges[] = [
                'min' => $min,
                'max' => $max,
            ];

            if (count($ranges) >= 8) {
                break;
            }
        }

        if ($ranges === []) {
            $ranges = $this->defaultAmountRangesForCurrency($currency, $operationThreshold);
        }

        return collect($ranges)
            ->values()
            ->map(function (array $range, int $index) use ($currency) {
                $minUnits = (string) Money::fromPrecision((string) $range['min'], $currency)->toUnits();
                $maxUnits = $range['max'] === null
                    ? null
                    : (string) Money::fromPrecision((string) $range['max'], $currency)->toUnits();

                return [
                    'key' => 'range_' . ($index + 1),
                    'label' => $range['max'] === null
                        ? 'от ' . $range['min']
                        : $range['min'] . ' - ' . $range['max'],
                    'min' => (string) $range['min'],
                    'max' => $range['max'] === null ? null : (string) $range['max'],
                    'min_units' => $minUnits,
                    'max_units' => $maxUnits,
                ];
            })
            ->all();
    }

    private function defaultAmountRangesForCurrency(string $currency, string $operationThreshold): array
    {
        return match ($currency) {
            'uah' => [
                ['min' => '300', 'max' => '999'],
                ['min' => '1000', 'max' => '29999'],
            ],
            'rub' => [
                ['min' => '1000', 'max' => '4999'],
                ['min' => '5000', 'max' => '500000'],
            ],
            default => [
                ['min' => $operationThreshold, 'max' => null],
            ],
        };
    }

    protected function getPageComponent(): string
    {
        return 'Admin/TraderAnalytics/Index';
    }

    protected function getIndexRouteName(): string
    {
        return 'admin.traders-analytics.index';
    }

    protected function getUpdateThresholdRouteName(): string
    {
        return 'admin.traders-analytics.operations-threshold.update';
    }

    protected function getSearchTradersRouteName(): string
    {
        return 'admin.traders-analytics.traders.search';
    }
}
