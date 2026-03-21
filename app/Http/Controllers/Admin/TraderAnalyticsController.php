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
        ]);

        $period_options = [
            'today' => ['label' => 'Сегодня', 'today' => true],
            '7d' => ['label' => '7 дней', 'days' => 7],
            '14d' => ['label' => '14 дней', 'days' => 14],
            '30d' => ['label' => '30 дней', 'days' => 30],
        ];

        $period = $validated['period'] ?? 'today';
        $selected_currency = $validated['currency'] ?? $default_currency;
        $operation_threshold = services()->settings()->getTraderAnalyticsOperationThresholdForCurrency(
            Currency::make($selected_currency)
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

        return Inertia::render('Admin/TraderAnalytics/Index', [
            'filters' => [
                'period' => $period,
                'currency' => $selected_currency,
            ],
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
        ]);
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
}
