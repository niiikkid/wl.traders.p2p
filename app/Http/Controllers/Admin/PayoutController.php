<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PayoutStatus;
use App\Exceptions\PayoutException;
use App\Exports\AdminPayoutsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payout\UpdateCurrencySettingsRequest;
use App\Http\Requests\Admin\Payout\UpdateStatusRequest;
use App\Http\Resources\Payout\AdminPayoutResource;
use App\Models\Payout\Payout;
use App\Models\User;
use App\Services\Money\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PayoutController extends Controller
{
    public function index(): Response
    {
        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $payouts = queries()->payout()->paginateForAdmin($filters);
        $payouts = AdminPayoutResource::collection($payouts);

        $traders = User::query()
            ->select(['id', 'name', 'email'])
            ->role('Trader')
            ->where('payouts_enabled', true)
            ->where('is_online', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Payout/Admin/Index', [
            'payouts' => $payouts,
            'filters' => $filters,
            'filtersVariants' => $filtersVariants,
            'traders' => $traders,
            'priorityAccessSettings' => services()->settings()->getPayoutPriorityAccessSettings(),
            'priorityAccessActiveCount' => $this->priorityAccessActiveCount(),
        ]);
    }

    public function export(): BinaryFileResponse
    {
        $filters = $this->getTableFilters();

        return Excel::download(
            new AdminPayoutsExport($filters),
            now()->format('Y-m-d_H-i-s').'_admin-payouts.xlsx'
        );
    }

    public function updateStatus(UpdateStatusRequest $request, Payout $payout): RedirectResponse
    {
        $status = PayoutStatus::from($request->validated('status'));
        $trader = $request->validated('trader_id')
            ? User::query()->find($request->validated('trader_id'))
            : null;

        try {
            services()->payout()->adminChangeStatus(
                payout: $payout,
                status: $status,
                trader: $trader,
                note: $request->validated('note') ?? null,
            );
        } catch (PayoutException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->back()->with('message', 'Статус выплаты обновлён.');
    }

    public function settingsData(): JsonResponse
    {
        $currencies = Currency::getAll()
            ->map(fn (Currency $currency) => [
                'code' => strtoupper($currency->getCode()),
                'name' => $currency->getName(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'currencies' => $currencies,
                'settings' => services()->settings()->getPayoutCurrencySettings(),
                'priority_access' => services()->settings()->getPayoutPriorityAccessSettings(),
            ],
        ]);
    }

    public function updateSettings(UpdateCurrencySettingsRequest $request): JsonResponse
    {
        $priorityAccess = $request->validated('priority_access');

        services()->settings()->updatePayoutPriorityAccessSettings(
            enabled: (bool) $priorityAccess['enabled'],
            delayMinutes: (int) $priorityAccess['delay_minutes'],
            releaseWithoutOnlineTraders: (bool) $priorityAccess['release_without_online_traders'],
        );
        services()->settings()->updatePayoutCurrencySettings($request->validated('settings'));

        if (! (bool) $priorityAccess['enabled']) {
            services()->payout()->releaseAllPriorityAccess();
        }

        return response()->json([
            'success' => true,
        ]);
    }

    public function releasePriorityAccess(): JsonResponse
    {
        $releasedCount = services()->payout()->releaseAllPriorityAccess();

        return response()->json([
            'success' => true,
            'released_count' => $releasedCount,
        ]);
    }

    private function priorityAccessActiveCount(): int
    {
        return Payout::query()
            ->where('status', PayoutStatus::OPEN->value)
            ->whereNull('trader_id')
            ->where('priority_access_until', '>', now())
            ->count();
    }
}
