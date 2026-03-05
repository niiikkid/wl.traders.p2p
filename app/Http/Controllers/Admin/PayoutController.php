<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payout\UpdateCurrencySettingsRequest;
use App\Http\Requests\Admin\Payout\UpdateStatusRequest;
use App\Http\Resources\Payout\AdminPayoutResource;
use App\Models\Payout\Payout;
use App\Models\User;
use App\Enums\PayoutStatus;
use App\Exceptions\PayoutException;
use App\Services\Money\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PayoutController extends Controller
{
    public function index(): Response
    {
        $filters = $this->getTableFilters();
        $filtersVariants = $this->getFiltersData();

        $payouts = queries()->payout()->paginateForAdmin($filters);
        $payouts = AdminPayoutResource::collection($payouts);

        $traders = \App\Models\User::query()
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
        ]);
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
            ],
        ]);
    }

    public function updateSettings(UpdateCurrencySettingsRequest $request): JsonResponse
    {
        services()->settings()->updatePayoutCurrencySettings($request->validated('settings'));

        return response()->json([
            'success' => true,
        ]);
    }
}


