<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trader;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trader\Economy\StoreMonthRequest;
use App\Http\Requests\Trader\Economy\UpdateDayRequest;
use App\Models\TraderEconomyDay;
use App\Models\TraderEconomyMonth;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EconomyController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const DAY_FIELDS = [
        'rate',
        'start_balance',
        'card_uah',
        'end_balance',
        'exchange_balance',
        'circles',
        'arbitrage_usd',
        'expense_uah',
    ];

    public function index(Request $request): Response
    {
        $this->ensureEconomyAccess($request);

        $user = $request->user();

        $months = TraderEconomyMonth::query()
            ->where('user_id', $user->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get(['id', 'year', 'month']);

        $selectedMonthId = (int) $request->integer('month_id');
        $selectedMonth = $months->firstWhere('id', $selectedMonthId) ?? $months->first();

        $days = [];

        if ($selectedMonth !== null) {
            $daysInMonth = (int) CarbonImmutable::createFromDate(
                $selectedMonth->year,
                $selectedMonth->month,
                1,
            )->daysInMonth;

            $existingDays = TraderEconomyDay::query()
                ->where('trader_economy_month_id', $selectedMonth->id)
                ->get()
                ->keyBy('day');

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $record = $existingDays->get($day);

                $days[] = [
                    'day' => $day,
                    'rate' => $record?->rate,
                    'start_balance' => $record?->start_balance,
                    'card_uah' => $record?->card_uah,
                    'end_balance' => $record?->end_balance,
                    'exchange_balance' => $record?->exchange_balance,
                    'circles' => $record?->circles,
                    'arbitrage_usd' => $record?->arbitrage_usd,
                    'expense_uah' => $record?->expense_uah,
                ];
            }
        }

        return Inertia::render('Economy/Trader/Index', [
            'months' => $months->map(fn (TraderEconomyMonth $month) => [
                'id' => $month->id,
                'year' => $month->year,
                'month' => $month->month,
            ])->values(),
            'selectedMonth' => $selectedMonth ? [
                'id' => $selectedMonth->id,
                'year' => $selectedMonth->year,
                'month' => $selectedMonth->month,
            ] : null,
            'days' => $days,
        ]);
    }

    public function store(StoreMonthRequest $request): RedirectResponse
    {
        $this->ensureEconomyAccess($request);

        $user = $request->user();

        /** @var array{year: int, month: int} $validated */
        $validated = $request->validated();

        $month = TraderEconomyMonth::query()->firstOrCreate([
            'user_id' => $user->id,
            'year' => (int) $validated['year'],
            'month' => (int) $validated['month'],
        ]);

        return redirect()
            ->route('trader.economy.index', ['month_id' => $month->id])
            ->with('message', 'Месяц добавлен.');
    }

    public function updateDay(UpdateDayRequest $request, TraderEconomyMonth $month, int $day): RedirectResponse
    {
        $this->ensureEconomyAccess($request);

        abort_unless($month->user_id === $request->user()->id, 403);

        $daysInMonth = (int) CarbonImmutable::createFromDate(
            $month->year,
            $month->month,
            1,
        )->daysInMonth;

        abort_if($day < 1 || $day > $daysInMonth, 422);

        $validated = $request->validated();

        $payload = [];

        foreach (self::DAY_FIELDS as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field] !== null && $validated[$field] !== ''
                    ? (string) $validated[$field]
                    : null;
            }
        }

        TraderEconomyDay::query()->updateOrCreate(
            [
                'trader_economy_month_id' => $month->id,
                'day' => $day,
            ],
            $payload,
        );

        return redirect()->back();
    }

    public function destroy(Request $request, TraderEconomyMonth $month): RedirectResponse
    {
        $this->ensureEconomyAccess($request);

        abort_unless($month->user_id === $request->user()->id, 403);

        $month->delete();

        return redirect()
            ->route('trader.economy.index')
            ->with('message', 'Месяц удалён.');
    }

    private function ensureEconomyAccess(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->hasRole('Super Admin')) {
            return;
        }

        if ($user->hasRole('Trader') && ! $user->trader_economy_enabled) {
            abort(403, 'Страница «Экономика» для вашего аккаунта отключена.');
        }
    }
}
