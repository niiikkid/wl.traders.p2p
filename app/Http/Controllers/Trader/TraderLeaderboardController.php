<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trader;

use App\Http\Controllers\Controller;
use App\Services\Trader\TraderLeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TraderLeaderboardController extends Controller
{
    public function index(TraderLeaderboardService $leaderboardService): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            abort(401);
        }

        $validated = request()->validate([
            'top_type' => ['nullable', 'string', 'in:weekly,monthly'],
            'week_offset' => ['nullable', 'integer', 'min:0', 'max:1'],
            'month_offset' => ['nullable', 'integer', 'min:0', 'max:1'],
        ]);
        $topType = (string) ($validated['top_type'] ?? 'weekly');
        $weekOffset = (int) ($validated['week_offset'] ?? 0);
        $monthOffset = (int) ($validated['month_offset'] ?? 0);
        $payload = $topType === 'monthly'
            ? $leaderboardService->getMonthlyTop(null, (int) $user->id, 10, true, $monthOffset)
            : $leaderboardService->getWeeklyTop(null, (int) $user->id, 10, true, $weekOffset);
        $top = collect($payload['top'])
            ->map(function (array $item) use ($topType) {
                $base = [
                    'rank' => (int) $item['rank'],
                    'trader_id' => (int) $item['trader_id'],
                    'nickname' => (string) $item['nickname'],
                    'is_online' => (bool) $item['is_online'],
                ];

                if ($topType === 'monthly') {
                    return [
                        ...$base,
                        'total_amount_units' => (int) $item['total_amount_units'],
                        'total_amount_human' => (string) $item['total_amount_human'],
                    ];
                }

                return [
                    ...$base,
                    'operations_count' => (int) $item['operations_count'],
                    'avg_processing_seconds' => (int) $item['avg_processing_seconds'],
                    'avg_processing_human' => (string) $item['avg_processing_human'],
                ];
            })
            ->values()
            ->all();

        $currentTrader = $payload['current_trader'];
        $currentTraderPrepared = null;
        if ($currentTrader !== null) {
            $currentTraderPrepared = [
                'rank' => (int) $currentTrader['rank'],
                'trader_id' => (int) $currentTrader['trader_id'],
                'nickname' => (string) $currentTrader['nickname'],
                'is_online' => (bool) $currentTrader['is_online'],
            ];

            if ($topType === 'monthly') {
                $currentTraderPrepared['total_amount_units'] = (int) $currentTrader['total_amount_units'];
                $currentTraderPrepared['total_amount_human'] = (string) $currentTrader['total_amount_human'];
            } else {
                $currentTraderPrepared['operations_count'] = (int) $currentTrader['operations_count'];
                $currentTraderPrepared['avg_processing_seconds'] = (int) $currentTrader['avg_processing_seconds'];
                $currentTraderPrepared['avg_processing_human'] = (string) $currentTrader['avg_processing_human'];
            }
        }

        return response()->json([
            'top_type' => $topType,
            'week_offset' => $weekOffset,
            'month_offset' => $monthOffset,
            'period_start' => $payload['period_start'],
            'period_end' => $payload['period_end'],
            'reset_at' => $payload['reset_at'],
            'generated_at' => $payload['generated_at'],
            'top' => $top,
            'current_trader' => $currentTraderPrepared,
        ]);
    }

    public function updateHideName(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            abort(401);
        }

        $validated = $request->validate([
            'hide_name_in_trader_top' => ['required', 'boolean'],
        ]);

        $user->forceFill([
            'hide_name_in_trader_top' => (bool) $validated['hide_name_in_trader_top'],
        ])->save();

        return response()->json([
            'hide_name_in_trader_top' => (bool) $user->hide_name_in_trader_top,
        ]);
    }
}
