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

        $payload = $leaderboardService->getWeeklyTop(null, (int) $user->id, 10, true);
        $top = collect($payload['top'])
            ->map(fn (array $item) => [
                'rank' => (int) $item['rank'],
                'trader_id' => (int) $item['trader_id'],
                'nickname' => (string) $item['nickname'],
                'is_online' => (bool) $item['is_online'],
                'operations_count' => (int) $item['operations_count'],
                'avg_processing_seconds' => (int) $item['avg_processing_seconds'],
                'avg_processing_human' => (string) $item['avg_processing_human'],
            ])
            ->values()
            ->all();

        $currentTrader = $payload['current_trader'];
        $currentTraderPrepared = $currentTrader === null ? null : [
            'rank' => (int) $currentTrader['rank'],
            'trader_id' => (int) $currentTrader['trader_id'],
            'nickname' => (string) $currentTrader['nickname'],
            'is_online' => (bool) $currentTrader['is_online'],
            'operations_count' => (int) $currentTrader['operations_count'],
            'avg_processing_seconds' => (int) $currentTrader['avg_processing_seconds'],
            'avg_processing_human' => (string) $currentTrader['avg_processing_human'],
        ];

        return response()->json([
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
