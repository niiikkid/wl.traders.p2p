<?php

namespace App\Http\Controllers\Trader;

use App\Exceptions\PayoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trader\Payout\MarkSentRequest;
use App\Http\Resources\Payout\TraderPayoutResource;
use App\Models\Payout\Payout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayoutController extends Controller
{
    private const REFRESH_INTERVALS = [0, 15, 30, 60];

    public function index(Request $request): Response
    {
        if (! $request->user()->payouts_enabled) {
            abort(403, 'Выплаты для вашего аккаунта отключены.');
        }

        $refreshInterval = $this->sanitizeRefreshInterval($request->integer('refresh_interval', 15));

        $tab = $request->query('tab') === 'history' ? 'history' : 'stack';

        $orderBook = queries()->payout()->paginateStackForTrader(10, max(1, $request->integer('stack_page', 1)));
        $activePayouts = queries()->payout()->getActiveForTrader($request->user());
        $history = queries()->payout()->paginateHistoryForTrader($request->user());

        $orderBook->appends([
            'refresh_interval' => $refreshInterval,
            'tab' => $tab,
            'page' => $history->currentPage(),
        ]);
        $history->appends([
            'refresh_interval' => $refreshInterval,
            'tab' => $tab,
            'stack_page' => $orderBook->currentPage(),
        ]);

        return Inertia::render('Payout/Trader/Index', [
            'orderBook' => TraderPayoutResource::collection($orderBook),
            'activePayouts' => TraderPayoutResource::collection($activePayouts),
            'history' => TraderPayoutResource::collection($history),
            'activeListTab' => $tab,
            'refresh' => [
                'interval' => $refreshInterval,
                'options' => self::REFRESH_INTERVALS,
            ],
            'limits' => [
                'maxActive' => (int) ($request->user()->payout_active_payouts_limit ?? 1),
                'currentActive' => $activePayouts->count(),
            ],
        ]);
    }

    public function take(Payout $payout): RedirectResponse
    {
        try {
            services()->payout()->take($payout, request()->user());
        } catch (PayoutException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->back()->with('message', 'Выплата закреплена за вами.');
    }

    public function markSent(MarkSentRequest $request, Payout $payout): RedirectResponse
    {
        try {
            $receipts = $request->file('receipts', []);
            services()->payout()->markSent(
                $payout,
                $request->user(),
                $request->file('receipt'),
                is_array($receipts) ? $receipts : []
            );
        } catch (PayoutException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->back()->with('message', 'Статус выплаты обновлён.');
    }

    private function sanitizeRefreshInterval(?int $interval): int
    {
        $interval = $interval ?? 15;

        return in_array($interval, self::REFRESH_INTERVALS, true) ? $interval : 15;
    }
}
